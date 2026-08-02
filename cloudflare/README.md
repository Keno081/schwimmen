# Schwimmen auf Cloudflare Workers

Portierte Version des PHP-Spiels als Cloudflare Worker mit KV-Speicher statt
PHP-Sessions/Dateien. Spiellogik in `src/game.js`, HTML-Rendering in
`src/render.js`, Routing in `src/index.js`. Statische Dateien liegen in
`public/`.

## Voraussetzungen

- Node.js (https://nodejs.org) — auf diesem Rechner noch nicht installiert.
- Ein kostenloser Cloudflare-Account.

## Einrichtung

```bash
cd schwimmen/cloudflare
npm install
npx wrangler login
```

Danach eine KV-Namespace anlegen:

```bash
npx wrangler kv namespace create SCHWIMMEN_KV
```

Die Ausgabe enthält eine `id`. Diese in `wrangler.toml` bei
`REPLACE_WITH_KV_NAMESPACE_ID` eintragen.

## Lokal testen

```bash
npm run dev
```

Öffnet einen lokalen Server (Adresse wird im Terminal angezeigt).

## Deployen

```bash
npm run deploy
```

Danach ist das Spiel unter der von Wrangler ausgegebenen
`*.workers.dev`-URL erreichbar. Optional kann in Cloudflare eine eigene
Domain zugeordnet werden.

## Unterschiede zur PHP-Version

- Einzelspieler-Spielstand liegt in KV unter einem Cookie (`sid`) statt in
  PHP-Sessions.
- Mehrspieler-Spiele (`?spiel=CODE`) liegen in KV unter `game:<code>` und
  `claims:<code>` statt als Dateien in `spiele/`.
- KV ist "eventually consistent" — bei sehr schnellen gleichzeitigen Zügen
  im Mehrspielermodus kann es in seltenen Fällen zu kurzen Verzögerungen
  kommen. Für ein privates Spiel mit Freunden ist das unkritisch.
