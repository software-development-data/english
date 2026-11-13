# Vexon — Soloworks primary app

## English Quest companion source

The separate English Quest application from `soloworks.pro.zip` is now available under [`english-quest/`](./english-quest/README-GITHUB.md). It includes the supplied static frontend and PHP backend with identified credentials removed. See its import notes and runtime limitations before deployment. Vexon remains unchanged.

This repository is now centered on the Vexon web application. The complete Vexon source is packaged in [vexon-source-public-20261112.zip](./vexon-source-public-20261112.zip).

- Stack: Next.js 16, React, TypeScript, Prisma, SQLite.
- Runtime: Node.js 20.9+ with Next.js standalone output.
- The archive excludes the demo SQLite database and private environment configuration.
- English Quest and the payment site are separate deployments in the companion multisite package. English Quest credentials are not stored in this public repository.
- Payments remain disabled until a provider/bank account and server-side transaction verification are configured.

See the README inside the source archive for local setup and runtime configuration.
