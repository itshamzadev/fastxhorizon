# Coolify deployment files

The three application Dockerfiles are the deployment units:

- `backend/Dockerfile`, exposed internally as port `5000`.
- `frontend/Dockerfile`, exposed as HTTP port `80` and assigned `fastxhorizon.com`.
- `admin/Dockerfile`, exposed as HTTP port `80` and assigned `controlpanel.fastxhorizon.com`.

Use `PRODUCTION_DEPLOYMENT_GUIDE.md` at the repository root for the authoritative
Coolify setup, environment variables, migration and rollback procedure. The
compose file in the root is a reference and must not be treated as a secret store.
