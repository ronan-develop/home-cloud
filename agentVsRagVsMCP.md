# Agent vs RAG vs MCP — plan d'outillage local pour home-cloud

> Contexte : inspiré de `starterPackOPOCI` (Verint), un CLI qui résout le problème
> "1 105 tables SQL → impossible de tout envoyer à Copilot". Ce document adapte
> ce principe à home-cloud, un projet Symfony PHP d'~100 fichiers développé avec
> Claude Code (CLI natif, pas VS Code Copilot).

---

## 1. Comparaison des approches

### RAG (Retrieval-Augmented Generation)

**Principe** : indexer le codebase → à chaque question, chercher les chunks
pertinents → injecter uniquement ceux-là dans le contexte.

| Pour                                                | Contre                              |
|-----------------------------------------------------|-------------------------------------|
| Très efficace sur de gros volumes (1 000+ fichiers) | Overkill à ~100 fichiers            |
| Évite d'envoyer tout le code à chaque échange       | Setup lourd (embeddings, vector DB) |
| Recherche sémantique multilingue possible           | Index à maintenir à jour            |

**Verdict pour home-cloud** : TF-IDF suffit. Pas besoin de sentence-transformers
ni de Chroma/FAISS. Un simple JSON indexé + recherche par mots-clés couvre 90 %
des cas.

---

### Agent autonome

**Principe** : laisser l'IA planifier et exécuter elle-même les lectures de
fichiers, les recherches, les modifications — sans contexte pré-injecté.

| Pour                                 | Contre                                            |
|--------------------------------------|---------------------------------------------------|
| Zéro préparation                     | Consomme beaucoup de tokens (exploration répétée) |
| S'adapte à n'importe quelle question | Lent (appels séquentiels)                         |
| Claude Code fait déjà ça nativement  | Redécouvre le même contexte à chaque session      |

**Verdict pour home-cloud** : c'est le mode actuel — et il fonctionne. Le
problème est la re-découverte à froid de l'architecture à chaque conversation.
L'objectif n'est pas de le remplacer mais de l'accélérer.

---

### MCP local (Model Context Protocol)

**Principe** : exposer des **tools** via un serveur MCP que Claude Code appelle
directement dans la conversation — équivalent d'une API locale pour le codebase.

| Pour                                                     | Contre                           |
|----------------------------------------------------------|----------------------------------|
| Intégration native Claude Code (`.claude/settings.json`) | Serveur Python à démarrer        |
| 1 tool call remplace 8 lectures de fichiers              | Index à régénérer après refactor |
| Contexte précis, pas de sur-chargement                   |                                  |
| Persiste entre les sessions                              |                                  |

**Verdict pour home-cloud** : c'est l'approche retenue. Le MCP expose des tools
ciblés qui fournissent instantanément le graphe d'une entité, les conventions
pertinentes, ou les routes API — sans exploration manuelle.

---

## 2. Ce qui est adapté de starterPackOPOCI

| starterPackOPOCI                   | home-cloud                             | Adapté ?                   |
|------------------------------------|----------------------------------------|----------------------------|
| `schema-meta.json` (1 105 tables)  | `hc-index.json` (~100 classes PHP)     | ✅ Simplifié               |
| TF-IDF search daemon (port 7892)   | TF-IDF inline (pas de daemon)          | ✅ Sans daemon             |
| Sentence-transformers embeddings   | —                                      | ❌ Inutile à cette échelle |
| Router Haiku/Sonnet/Opus (`ts.py`) | Hints dans `hc-route.py`               | ✅ Optionnel               |
| `.vc_context.md` → Copilot `@file` | MCP tools → Claude Code natif          | ✅ Mieux                   |
| `vc make sp / query / improve`     | `hc-make.py entity / api / service`    | ✅ Adapté                  |
| Git hooks (commit-msg, pre-commit) | Déjà dans `.claude/git-conventions.md` | ➖ Déjà couvert            |
| Vocabulaire FR→EN (400+ termes)    | —                                      | ❌ Code déjà cohérent      |

---

## 3. Plan d'implémentation

### Phase 1 — Index du codebase (`tools/index-build.py`)

**Durée estimée : 1-2h**

Script Python qui parse les fichiers PHP par regex et génère `tools/hc-index.json`.

Structure de l'index :
```json
{
  "File": {
    "type": "Entity",
    "file": "src/Entity/File.php",
    "description": "Fichier stocké, appartient à un User, lié à un Folder",
    "relations": ["User", "Folder"],
    "service": "CreateFileService",
    "states": ["FileProcessor", "FileProvider"],
    "repository": "FileRepository",
    "api_output": "FileOutput",
    "controller": "FileDownloadController",
    "tests": ["tests/Entity/...", "tests/Service/..."]
  }
}
```

Ce que le script extrait automatiquement :
- Type de classe (Entity / Service / Controller / State / Repository / Interface…)
- Relations Doctrine (`@ORM\ManyToOne`, `#[ORM\...]`)
- Interfaces implémentées
- Associations par convention de nommage (`FileProcessor` → entité `File`)

Commande : `python tools/index-build.py` — à relancer après un refactor majeur.

---

### Phase 2 — Serveur MCP local (`tools/hc-mcp.py`)
**Durée estimée : 2-3h**

Serveur MCP Python (bibliothèque `mcp` officielle Anthropic) enregistré dans
`.claude/settings.json` :

```json
{
  "mcpServers": {
    "home-cloud": {
      "command": "python",
      "args": ["tools/hc-mcp.py"]
    }
  }
}
```

**4 tools exposés :**

#### `search_code(query: str) → list`
TF-IDF sur `hc-index.json`. Retourne les classes les plus pertinentes pour une
description en langage naturel.
```
search_code("upload de fichier avec validation")
→ [CreateFileService, FileUploadController, FilenameValidator, StorageService]
```

#### `get_entity_graph(entity: str) → dict`
Retourne le graphe complet d'une entité : Entity + Repo + Service + State +
Controller + ApiOutput + tests associés — avec leurs chemins.
```
get_entity_graph("File")
→ { entity: "src/Entity/File.php", service: "src/Service/CreateFileService.php", ... }
```

#### `get_conventions(topic: str) → str`
Extrait les sections pertinentes des fichiers `.claude/*.md` selon le topic.
Topics supportés : `tdd`, `uuid`, `git`, `entity`, `api`, `frontend`, `deploy`.
```
get_conventions("entity")
→ [contenu de architecture.md § UUID + tdd.md § RED/GREEN/REFACTOR]
```

#### `get_api_routes() → list`
Liste toutes les routes API Platform avec leurs State processors/providers,
méthodes HTTP, et entités associées.
```
get_api_routes()
→ [{ route: "/api/files", methods: ["GET","POST"], state: "FileProvider/FileProcessor" }, ...]
```

---

### Phase 3 — Templates de tâches (`tools/hc-make.py`)
**Durée estimée : 1-2h**

Équivalent des `vc make sp` / `vc make query`. Génère un prompt contextualisé
et token-safe pour les tâches répétitives.

```bash
python tools/hc-make.py entity      # Nouvelle entité : UUID v7, TDD, Repository
python tools/hc-make.py api         # ApiResource + StateProcessor + StateProvider + Output
python tools/hc-make.py service     # Service + Interface + test TDD
python tools/hc-make.py test        # Squelette de test basé sur les patterns existants
```

Chaque commande :
1. Appelle `search_code` pour trouver les patterns similaires dans le codebase
2. Appelle `get_conventions` pour les règles applicables
3. Assemble un prompt court avec uniquement le contexte utile
4. L'affiche dans le terminal (ou l'écrit dans `.hc_context.md`)

---

### Phase 4 — Router de modèle (`tools/hc-route.py`) — optionnel
**Durée estimée : 30 min**

Adapté de `ts.py`. Classifie la complexité d'un prompt pour suggérer le modèle.

| Complexité | Modèle | Exemples |
|---|---|---|
| Simple | Haiku 4.5 | commit, rename, fix CSS, list |
| Moyenne | Sonnet 4.6 | ajout entité, refactor service, test TDD |
| Complexe | Opus 4.8 | architecture, nouveau module, migration DB |

Usage : `python tools/hc-route.py --prompt "refactorer FolderService pour..."`
→ `→ sonnet | Complexité moyenne | Contexte : architecture.md, tdd.md`

---

## 4. Ce qui N'est PAS à implémenter

- **Vector DB** (Chroma, FAISS, pgvector) — inutile à ~100 fichiers
- **Daemon mode** (port ouvert en background) — le cold start est négligeable
- **Vocabulaire de traduction** — le code est déjà homogène
- **Intégration GitLab/GitHub API** — `gh` CLI + hooks `.claude/` couvrent le besoin
- **Embeddings multilingues** — TF-IDF sur les noms de classes suffit

---

## 5. Priorisation

```
Phase 1 — Index          ██████████  Base de tout, 0 dépendance
Phase 2 — MCP            █████████   Intégration native Claude Code
Phase 3 — Templates      █████       Accélère les tâches répétitives
Phase 4 — Router         ███         Nice-to-have
```

**Impact attendu :**
- Moins de re-lecture de fichiers en début de conversation
- `get_entity_graph` remplace ~8 appels `Read` séquentiels
- `get_conventions` évite de charger 495 lignes de docs à chaque fois
- Économie estimée : 30-50 % de tokens par session de dev

---

## 6. Structure cible des fichiers

```
home-cloud/
├── tools/
│   ├── index-build.py      # Génère hc-index.json
│   ├── hc-index.json       # Index généré (gitignored ou commité)
│   ├── hc-mcp.py           # Serveur MCP local
│   ├── hc-make.py          # Templates de tâches
│   └── hc-route.py         # Router de modèle (optionnel)
├── .claude/
│   └── settings.json       # Ajouter l'entrée mcpServers
└── agentVsRagVsMCP.md      # Ce fichier
```

---

## 7. Démarrage rapide (une fois implémenté)

```bash
# 1. Générer / mettre à jour l'index
python tools/index-build.py

# 2. Démarrer Claude Code (le MCP se lance automatiquement)
claude

# 3. Dans la conversation — Claude appelle les tools automatiquement
# ou on peut les invoquer explicitement :
# "utilise get_entity_graph('Album') avant de continuer"
```
