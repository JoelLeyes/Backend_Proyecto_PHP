# Backend Proyecto PHP

Notas de despliegue 1: 
- LiveKit se configura desde `k8s/backend-secret.yaml`.
- El backend usa `LIVEKIT_URL`, `LIVEKIT_API_KEY` y `LIVEKIT_API_SECRET` para generar tokens de videollamada.
- Para OAuth social, el backend necesita `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` en el secreto/entorno de despliegue.

comentario