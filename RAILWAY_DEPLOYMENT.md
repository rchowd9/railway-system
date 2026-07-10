# Railway Deployment Guide for Railway System

## Prerequisites
- Railway account (create at https://railway.app)
- GitHub repository with your code
- Docker installed locally (for testing)

## Step 1: Push Code to GitHub

```bash
cd railway-system
git add .
git commit -m "Add Railway configuration with multiple services"
git push -u origin main
```

## Step 2: Create Railway Project

1. Go to https://railway.app/dashboard
2. Click "New Project"
3. Select "Deploy from GitHub"
4. Connect your GitHub account and select the railway-system repository

## Step 3: Railway Will Auto-Detect Configuration

Railway will read `railway.toml` and automatically:
- Create Go service (go-tracking-core)
- Create PHP service (php-control-plane)
- Create Python service (transit-python-cluster)
- Provision Redis database
- Provision MySQL database
- Connect all services with environment variables

## Step 4: Manual Service Setup (If Auto-Detection Fails)

If Railway doesn't auto-detect from railway.toml, manually add services:

### Add Go Service:
1. Click "New Service" → "GitHub Repo"
2. Select your repo
3. Set Root Directory: `go-tracking-core`
4. Select "Dockerfile" as build method
5. Add environment variables:
   - `REDIS_ADDR`: Will be set automatically
   - `PORT`: 8080

### Add PHP Service:
1. Click "New Service" → "GitHub Repo"
2. Select your repo
3. Set Root Directory: `php-control-plane`
4. Select "Dockerfile" as build method
5. Add environment variables:
   - `DB_HOST`: Will be set from MySQL plugin
   - `DB_NAME`: railway_db
   - `DB_USER`: railway_user
   - `DB_PASS`: Will be set from MySQL plugin
   - `REDIS_HOST`: Will be set from Redis plugin
   - `REDIS_PORT`: Will be set from Redis plugin

### Add Python Service:
1. Click "New Service" → "GitHub Repo"
2. Select your repo
3. Set Root Directory: `transit-python-cluster`
4. Select "Dockerfile" as build method
5. Add environment variables:
   - `REDIS_HOST`: Will be set from Redis plugin
   - `REDIS_PORT`: Will be set from Redis plugin

## Step 5: Add Redis & MySQL Plugins

### Add Redis:
1. In your Railway project, click "Add Plugin"
2. Select "Redis"
3. Railway auto-injects connection variables to all services

### Add MySQL:
1. In your Railway project, click "Add Plugin"
2. Select "MySQL"
3. Set:
   - Database: `railway_db`
   - Username: `railway_user`
4. Railway auto-injects connection variables to all services

## Step 6: Initialize MySQL Database

1. Go to MySQL plugin → "Connect" tab
2. Use the connection string to connect
3. Run `php-control-plane/schema.sql`

Or via Railway CLI:
```bash
railway run mysql -h <host> -u railway_user -p < php-control-plane/schema.sql
```

## Step 7: Deploy

Once all services are configured, Railway automatically deploys when you push to GitHub.

Monitor deployment status in the Railway dashboard for each service.

## Local Testing (Before Deploying)

```bash
docker-compose up
```

This tests all services locally:
- Go: http://localhost:8080
- PHP: http://localhost:80
- Redis: localhost:6379
- MySQL: localhost:3306

## Environment Variables Reference

Railway automatically injects these variables (no need to manually set):

```
# From Redis Plugin
Redis.host          → REDIS_HOST
Redis.port          → REDIS_PORT

# From MySQL Plugin
MySQL.MYSQL_HOST     → DB_HOST
MySQL.MYSQL_DATABASE → DB_NAME (railway_db)
MySQL.MYSQL_USER     → DB_USER (railway_user)
MySQL.MYSQL_PASSWORD → DB_PASS
```

## Service Endpoints

After deployment, Railway provides public URLs:
- **Go Tracker**: `https://your-project-go-tracking-core-prod.up.railway.app:8080`
- **PHP Dashboard**: `https://your-project-php-control-plane-prod.up.railway.app`
- **Python Bot**: Runs in background (no public URL needed)

## Troubleshooting

**Error: "Railpack could not determine how to build"**
- Ensure railway.toml is in root directory
- Try adding services manually via Railway dashboard
- Check that Dockerfiles exist in correct locations

**Redis connection fails:**
- Go to Redis plugin → "Variables" tab
- Copy the provided host and port
- Verify they're set in service environment variables

**MySQL connection fails:**
- Ensure MySQL plugin is added to project
- Check that DB_HOST uses plugin connection string
- Verify username/password match MySQL plugin settings
- Run schema.sql to initialize database

**PHP shows database error:**
- Check that MySQL database `railway_db` exists
- Verify DB_USER has proper permissions
- Ensure schema.sql has been executed

**Python bot not connecting:**
- Verify Redis is running (check Redis plugin status)
- Check REDIS_HOST and REDIS_PORT in Python service variables
- Review Python service logs for connection errors

## Production Checklist

- [ ] Set strong MySQL password (Railway generates one automatically)
- [ ] Enable HTTPS (Railway provides free SSL automatically)
- [ ] Configure scheduled MySQL backups in Railway dashboard
- [ ] Review service resource usage and scale up if needed
- [ ] Set up custom domain (in Railway settings)
- [ ] Test database failover and recovery
- [ ] Configure monitoring and alerts
- [ ] Review and test database schema
- [ ] Set up error tracking (Sentry, etc.)
- [ ] Test all WebSocket connections

