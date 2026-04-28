# BJMP Personnel Management System - Railway Deployment Guide

## 🚀 Quick Start

### 1. Railway Setup
1. Create Railway account at [railway.app](https://railway.app)
2. Install Railway CLI: `npm install -g @railway/cli`
3. Login: `railway login`
4. Create new project: `railway new`

### 2. GitHub Integration
1. Fork this repository to your GitHub account
2. Connect GitHub to Railway project
3. Set up automatic deployment

### 3. Environment Variables
Set these environment variables in Railway dashboard:

#### Database Variables
- `DB_HOST` - Your Railway database host
- `DB_NAME` - `railway`
- `DB_USER` - Your database username
- `DB_PASSWORD` - Your database password
- `DB_PORT` - `3306`

#### Application Variables
- `APP_NAME` - `BJMP Personnel Management System`
- `BASE_URL` - Your Railway app URL
- `ENVIRONMENT` - `production`

#### Security Variables
- `HASH_COST` - `12`
- `LOGIN_TIMEOUT` - `300`
- `MAX_LOGIN_ATTEMPTS` - `5`

## 📋 Deployment Steps

### Option 1: GitHub Actions (Recommended)
1. Set GitHub secrets:
   - `RAILWAY_TOKEN`: Your Railway API token
   - `RAILWAY_SERVICE_NAME`: Your Railway service name

2. Push to main branch to trigger deployment

### Option 2: Railway CLI
```bash
# Deploy to Railway
railway up

# Set environment variables
railway variables set DB_HOST=your-host
railway variables set DB_PASSWORD=your-password
# ... set all variables
```

### Option 3: Railway Dashboard
1. Connect GitHub repository
2. Configure environment variables
3. Deploy manually

## 🔧 Configuration Files

- `Dockerfile` - Container configuration
- `railway.json` - Railway deployment settings
- `.github/workflows/deploy.yml` - GitHub Actions workflow

## 🗄️ Database Setup

### Automatic Setup
The system includes a setup script that creates:
- All required tables
- Default admin account
- Initial system settings

### Manual Setup
Access: `https://your-app.railway.app/setup/create_admin.php`

## 🔐 Default Credentials

**Username:** `admin`  
**Password:** `Admin@123`

## 📱 Post-Deployment Checklist

- [ ] Test admin login
- [ ] Change default password
- [ ] Test user registration
- [ ] Verify file uploads work
- [ ] Test all role permissions
- [ ] Configure custom domain (optional)

## 🐛 Troubleshooting

### Common Issues
1. **Database Connection**: Check environment variables
2. **Permission Denied**: Verify file permissions in Dockerfile
3. **Login Issues**: Run setup script to recreate admin account
4. **Upload Issues**: Check uploads directory permissions

### Debug Tools
- `/debug/login_debug.php` - Login debugging
- `/setup/fix_login.php` - Account repair
- `/setup/create_admin.php` - Admin creation

## 🔄 Updates

Pushing to main branch automatically triggers deployment via GitHub Actions.

## 📞 Support

For deployment issues:
1. Check Railway logs
2. Verify environment variables
3. Run debug scripts
4. Check GitHub Actions workflow

## 🔒 Security Notes

- Change default admin password immediately
- Use HTTPS in production
- Monitor Railway logs for suspicious activity
- Keep dependencies updated
