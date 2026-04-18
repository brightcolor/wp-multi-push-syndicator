# Architecture

This page mirrors `/docs/ARCHITECTURE.md` and describes module boundaries, flow, and extension points.

## Components

- Core
- Admin
- Repository
- Service
- Scheduling
- API
- Media
- Update
- Logging

## Data flow

Post save -> target selection -> schedule -> transform -> media upload -> remote create/update -> status + logs.