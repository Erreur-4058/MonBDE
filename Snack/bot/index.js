// bot/index.js — Bot Discord Snack
import 'dotenv/config';
import * as Discord from 'discord.js';
import { createServer } from 'http';

const {
  Client,
  GatewayIntentBits,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
  EmbedBuilder,
  Events,
} = Discord;

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
  ],
});

// --- API HELPERS ---
async function apiRequest(path, method = 'GET', body = null) {
    const url = `${process.env.API_BASE_URL}/${path}`;
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Bot-Token': process.env.API_BOT_TOKEN ?? '',
        }
    };
    if (body) options.body = JSON.stringify(body);
    const res = await fetch(url, options);
    return await res.json();
}

async function updateCommande(numero, payload) {
    return await apiRequest('statut.php', 'POST', { numero, ...payload });
}

async function getCommande(numero) {
    const res = await apiRequest(`get_commande.php?numero=${numero}`);
    return res.success ? res.data : null;
}

// --- UTILITAIRES ---

/**
 * Transforme la liste des produits en une chaîne de caractères
 * Exemple: "1x Jambon-Beurre, 2x Coca-Cola"
 */
function formatProductsList(lignes) {
    if (!lignes || lignes.length === 0) return "Commande Snack";
    const texte = lignes.map(l => `${l.quantite}x ${l.nom_produit}`).join(', ');
    // On limite à 120 caractères (limite SumUp)
    return texte.length > 120 ? texte.substring(0, 117) + "..." : texte;
}

/**
 * URL vers le pont PHP pour ouvrir l'app SumUp
 */
function getBridgeUrl(montant, ref) {
    const baseUrl = process.env.API_BASE_URL; 
    return `${baseUrl}/sumup_bridge.php?amount=${Number(montant).toFixed(2)}&ref=${encodeURIComponent(ref)}`;
}

// --- VISUELS ---

function buildEmbed(data, statutTexte = "En attente de paiement") {
  const tn = Number(data.total_normal || 0);
  const ta = Number(data.total_adherent || 0);

    const numeroAffiche = data.numero.includes('-') ? data.numero.split('-')[1] : data.numero;
  const embed = new EmbedBuilder()
    .setTitle(`🛒 Commande — #${numeroAffiche}`) // Affiche "Commande — #5"
    .setColor(data.statut === 'paye' ? 0x22C55E : 0xF59E0B)
    .setDescription(`**Statut :** ${statutTexte}`)
    .addFields(
        { name: '👤 Client', value: data.discord_id ? `<@${data.discord_id}>` : data.prenom, inline: true },
        { name: '💶 Prix Normal', value: `${tn.toFixed(2)}€`, inline: true },
        { name: '💳 Prix Adhérent', value: `${ta.toFixed(2)}€`, inline: true }
    )
    .setTimestamp();

  if (data.lignes) {
    const lignesText = data.lignes.map(l => `• ${l.quantite}× **${l.nom_produit}**`).join('\n');
    embed.addFields({ name: '📋 Articles', value: lignesText || 'Aucun', inline: false });
  }

  return embed;
}

function getButtons(commande) {
  const tn = Number(commande.total_normal || 0);
  const ta = Number(commande.total_adherent || 0);
  
  // On prend juste le numéro court (ex: le "1" de "20260326-1")
  const numCourt = commande.numero.split('-')[1] || commande.numero;
  const listeProduits = formatProductsList(commande.lignes);

  const row1 = new ActionRowBuilder().addComponents(
    new ButtonBuilder()
      .setLabel(`App Adhérent (${ta.toFixed(2)}€)`)
      .setStyle(ButtonStyle.Link)
      .setURL(getBridgeUrl(ta, `#${numCourt} ADH: ${listeProduits}`)), 
    new ButtonBuilder()
      .setLabel(`App Normal (${tn.toFixed(2)}€)`)
      .setStyle(ButtonStyle.Link)
      .setURL(getBridgeUrl(tn, `#${numCourt} NORM: ${listeProduits}`)),
    new ButtonBuilder()
      .setCustomId(`pay_mode:especes:${commande.numero}`)
      .setLabel('💵 Espèces')
      .setStyle(ButtonStyle.Primary)
  );

  const row2 = new ActionRowBuilder().addComponents(
    new ButtonBuilder()
      .setCustomId(`annuler:none:${commande.numero}`)
      .setLabel('❌ Annuler la commande')
      .setStyle(ButtonStyle.Danger)
  );

  return [row1, row2];
}

// --- EVENTS ---

client.once(Events.ClientReady, () => {
  console.log(`✅ Bot connecté : ${client.user.tag}`);
});

client.on(Events.InteractionCreate, async (interaction) => {
  if (!interaction.isButton()) return;
  const [action, valeur, numero] = interaction.customId.split(':');

  try {
    if (action === 'annuler') {
      await updateCommande(numero, { statut: 'annule' });
      await interaction.update({ content: '❌ Commande annulée.', embeds: [], components: [] });
    } 
    else if (action === 'pay_mode' && valeur === 'especes') {
      await updateCommande(numero, { paiement: 'especes', statut: 'paye' });
      const cmd = await getCommande(numero);
      await interaction.update({ 
        content: '✅ Paiement validé.',
        embeds: [buildEmbed(cmd, "✅ Payé en espèces")], 
        components: [] 
      });
    }
  } catch (err) {
    console.error("Erreur Interaction:", err);
    await interaction.followUp({ content: 'Erreur technique.', ephemeral: true });
  }
});

// --- SERVEUR HTTP ---

const httpServer = createServer(async (req, res) => {
  if (req.method === 'POST' && req.url === '/nouvelle-commande') {
    let body = '';
    req.on('data', d => body += d);
    req.on('end', async () => {
      try {
        const commande = JSON.parse(body);
        const channel = await client.channels.fetch(process.env.DISCORD_CHANNEL_COMMANDES);
        
        const embed = buildEmbed(commande);
        const buttons = getButtons(commande);
        
        const msg = await channel.send({ 
            content: `🔔 **Nouvelle commande de ${commande.prenom}**`,
            embeds: [embed], 
            components: buttons 
        });

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true, message_id: msg.id }));
      } catch (e) {
        console.error("Erreur HTTP Server:", e);
        res.writeHead(500).end(e.message);
      }
    });
  } else {
      res.writeHead(404).end();
  }
});

httpServer.listen(process.env.BOT_HTTP_PORT || 3001, () => {
    console.log(`🌐 Serveur Bot en attente sur le port ${process.env.BOT_HTTP_PORT || 3001}`);
});

client.login(process.env.DISCORD_TOKEN);