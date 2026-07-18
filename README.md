# Railway System — Real-Time Transit Monitor

Lightweight polyglot demo that simulates and displays an MTA-like real-time feed.

Components
- `go-tracking-core/` — Go-based tracker service (WebSocket/HTTP)
- `php-control-plane/` — PHP front-end and SSE endpoints (`stream.php`, `timetable.php`, admin UI)
- `transit-python-cluster/` — Python ingestion/bridge that writes simulated feed into Redis

Key improvements (recent)
- Server-side `eta_seconds` computed by the Python bridge so clients render authoritative ETAs.
- SSE payload wrapped with `server_ts` (server epoch seconds) and `trains` array; client uses this to calculate a `clockDelta` and avoid clock-skew issues.
- Client (`timetable.php`) prefers server `eta_seconds` (and uses a synced server time) for accurate countdowns; backwards-compatible with older payloads.
- Admin injection fixes: proper 12-hour time formatting and validations for overrides.

Prerequisites
- Redis (local or remote) — used for feed storage and pub/sub
- Python 3.10+ (3.11 recommended)
- PHP 8+ (for the control plane)
- Go 1.21+ (for the tracker service)
- XAMPP or another local PHP/Apache stack if you prefer an Apache-based server

Quick local run (minimal)

1. Start Redis (example using Docker):

```powershell
docker run --rm -p 6379:6379 redis:7-alpine
```

Or run your local `redis-server`.

2. Start the Python bridge (writes `mta-live-schedule` to Redis):

```powershell
python transit-python-cluster/mta_bridge.py
```

3. Serve the PHP control plane

Option A: PHP built-in server

```powershell
cd php-control-plane
php -S 0.0.0.0:8000
```

Option B: XAMPP/Apache

1. Copy the `php-control-plane` contents into your XAMPP `htdocs` folder, or create a symlink to it.
2. Start Apache from the XAMPP Control Panel.
3. Open `http://localhost/php-control-plane/timetable.php` or configure a virtual host for a custom path.

4. Open the UI in your browser:

- `http://localhost:8000/timetable.php`
- Admin: `http://localhost:8000/admin.php`

5. (Optional) Run the Go service:

```powershell
cd go-tracking-core
go run .
# or build: go build -o tracker-service . && .\tracker-service
```

Notes & Troubleshooting
- Ports: PHP built-in uses 8000 above; adjust if already in use.
- Redis connection: code expects Redis on `127.0.0.1:6379`. If using Docker, ensure the process can reach Redis (use host networking or map ports as above).
- If the UI shows incorrect countdown values, check the browser console for `clockDelta` messages and ensure the SSE `stream.php` is reachable and that `server_ts` is present in the payload.
- Admin overrides are stored in Redis under `mta-admin-override` and are normalized by the Python bridge when present.

Development tips
- To add server-side ETA logic or sequence counters, update `transit-python-cluster/mta_bridge.py` and `php-control-plane/stream.php` together so the client retains a simple parsing model.
- For production deployments, containerize each service and provide secrets for DB/Redis via environment variables or a secrets manager.

License
- MIT

Contact
- If you want me to add CI, container manifests, or Railway deployment files again, tell me which platform and I'll prepare them.

