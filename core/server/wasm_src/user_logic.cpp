#include <emscripten/bind.h>
#include <string>

using namespace emscripten;

std::string get_welcome_message(std::string name, std::string role) {
    return "Bienvenue " + name + " (Session: " + role + ") - Moteur WASM Actif";
}

std::string get_version() {
    return "1.0.0-wasm";
}

EMSCRIPTEN_BINDINGS(my_module) {
    function("get_welcome_message", &get_welcome_message);
    function("get_version", &get_version);
}