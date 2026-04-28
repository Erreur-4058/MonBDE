// bot/index.js — Bot Discord Snack

import 'dotenv/config';
import {
  Client,
  GatewayIntentBits,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  EmbedBuilder,
  Events,
} from 'discord.js';
import { createServer } from 'http';

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
  ],
});

async function updateCommande(numero, payload) {
  const url = `${process.env.API_BASE_URL}/statut.php`;
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Bot-Token': process.env.API_BOT_TOKEN ?? '',
    },
    body: JSON.stringify({ numero, ...payload }),
  });
  const text = await res.text();
  let json;
  try { json = JSON.parse(text); }
  catch { throw new Error(`Réponse PHP invalide (${res.status}): ${text.slice(0, 200)}`); }
  if (!json.success) throw new Error(json.error ?? 'API error');
  return json.data;
}

async function supprimerMessage(interaction) {
  try { await interaction.message.delete(); }
  catch { await interaction.editReply({ components: [] }); }
}

function sumupUrl(montant, ref) {
  const merchantId = process.env.SUMUP_MERCHANT_ID ?? '';
  if (!merchantId) return 'https://sumup.com';
  return `https://pay.sumup.com/b2c/${merchantId}?amount=${Number(montant).toFixed(2)}&currency=EUR&ref=${encodeURIComponent(ref)}`;
}

function buildEmbed(data) {
  const embed = new EmbedBuilder()
    .setTitle(`🛒 Commande — ${data.numero}`)
    .setColor(0xF59E0B)
    .setTimestamp();

  embed.addFields({
    name: '👤 Client',
    value: data.discord_id ? `${data.prenom} (<@${data.discord_id}>)` : data.prenom,
    inline: true,
  });

  if (data.lignes && data.lignes.length) {
    const lignesText = data.lignes
      .map(l => {
        const st = Number(l.sous_total ?? l.sous_total_n ?? 0);
        return `• ${l.quantite}× **${l.nom_produit}** — ${st.toFixed(2)} €`;
      })
      .join('\n');
    embed.addFields({ name: '📋 Articles', value: lignesText, inline: false });
  }

  const tn = Number(data._total_normal ?? data.total ?? 0);
  const ta = Number(data._total_adherent ?? 0);

  embed.addFields(
    { name: '💶 Prix non-adhérent', value: `${tn.toFixed(2)} €`, inline: true },
    { name: '💳 Prix adhérent',     value: `${ta.toFixed(2)} €`, inline: true },
  );

  return embed;
}

function buildButtons(numero, totalNormal, totalAdherent) {
  const row = new ActionRowBuilder().addComponents(
    new ButtonBuilder()
      .setLabel('💳 Adhérent — Payer via SumUp')
      .setStyle(ButtonStyle.Link)
      .setURL(sumupUrl(totalAdherent, `${numero}-ADH`)),
    new ButtonBuilder()
      .setLabel('👤 Non-adhérent — Payer via SumUp')
      .setStyle(ButtonStyle.Link)
      .setURL(sumupUrl(totalNormal, `${numero}-NORM`)),
    new ButtonBuilder()
      .setCustomId(`annuler:${numero}`)
      .setLabel('❌ Annuler la commande') 
      .setStyle(ButtonStyle.Danger),
  );
  return [row];
}

client.once(Events.ClientReady, () => {
  console.log(`✅ Bot connecté en tant que ${client.user.tag}`);
});

client.on(Events.InteractionCreate, async (interaction) => {
  if (!interaction.isButton()) return;
  const [action, numero] = interaction.customId.split(':');
  if (!numero) return;

  await interaction.deferUpdate();

  try {
    if (action === 'annuler') {
      await updateCommande(numero, { statut: 'annule' });
      await supprimerMessage(interaction);
    }
  } catch (err) {
    console.error(`[BOUTON ${action}]`, err);
    try {
      await interaction.followUp({ content: `❌ Erreur : ${err.message}`, ephemeral: true });
    } catch {}
  }
});

const PORT = process.env.BOT_HTTP_PORT ?? 3001;

const httpServer = createServer(async (req, res) => {
  if (req.method !== 'POST' || req.url !== '/nouvelle-commande') {
    res.writeHead(404).end('Not found');
    return;
  }

  const auth = req.headers['x-bot-token'] ?? '';
  if (auth !== (process.env.API_BOT_TOKEN ?? '')) {
    res.writeHead(401).end('Unauthorized');
    return;
  }

  let body = '';
  req.on('data', d => (body += d));
  req.on('end', async () => {
    try {
      const commande = JSON.parse(body);
      const channel = await client.channels.fetch(process.env.DISCORD_CHANNEL_COMMANDES);
      if (!channel?.isTextBased()) throw new Error('Canal introuvable');

      const totalNormal   = Number(commande.total_normal ?? 0);
      const totalAdherent = Number(commande.total_adherent ?? 0);

      const embed = buildEmbed({
        ...commande,
        statut: 'en_attente',
        lignes: commande.lignes ?? [],
        _total_normal:   totalNormal,
        _total_adherent: totalAdherent,
      });

      const buttons = buildButtons(commande.numero, totalNormal, totalAdherent);
      const msg = await channel.send({ embeds: [embed], components: buttons });

      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: true, message_id: msg.id }));

    } catch (err) {
      console.error('[HTTP]', err);
      res.writeHead(500).end(JSON.stringify({ ok: false, error: err.message }));
    }
  });
});

httpServer.listen(PORT, () => {
  console.log(`🌐 Serveur HTTP bot démarré sur le port ${PORT}`);
});

client.login(process.env.DISCORD_TOKEN);
