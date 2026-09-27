const JSDOMEnvironment = require('jest-environment-jsdom').default;

class CustomJSDOMEnvironment extends JSDOMEnvironment {
  async setup() {
    await super.setup();
    if (typeof this.global.TextEncoder === 'undefined') {
      const { TextEncoder, TextDecoder } = require('util');
      this.global.TextEncoder = TextEncoder;
      this.global.TextDecoder = TextDecoder;
    }
    // jsdom n'expose pas crypto.subtle (SubtleCrypto) — utilisé par
    // chunked-upload.js pour hasher le premier chunk d'un fichier avant de
    // reprendre un upload interrompu (#481). Le navigateur réel l'expose
    // nativement ; seul l'environnement de test doit être complété.
    if (typeof this.global.crypto?.subtle === 'undefined') {
      const { webcrypto } = require('node:crypto');
      this.global.crypto.subtle = webcrypto.subtle;
    }
    // Le Blob/File de jsdom n'implémentent pas .arrayBuffer() (API standard,
    // supportée par tout navigateur réel) — remplacés par les classes
    // natives de Node, qui l'implémentent (File hérite de Blob, les deux
    // doivent être remplacés ensemble sinon File.slice() renvoie un Blob de
    // jsdom malgré tout).
    const { Blob, File } = require('node:buffer');
    this.global.Blob = Blob;
    this.global.File = File;
  }
}

module.exports = CustomJSDOMEnvironment;
