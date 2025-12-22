# Environment Variables Configuration

## Overview

This project uses `react-native-dotenv` to manage environment variables across different environments (development, staging, production).

## Setup

### 1. Create your local .env file

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

### 2. Fill in your values

Edit `.env` with your actual configuration values (API keys, endpoints, etc.)

**⚠️ IMPORTANT: Never commit `.env` files with real credentials to version control!**

## Usage in Code

Import environment variables using the `@env` module:

```typescript
import { API_BASE_URL, ENABLE_DEBUG_MODE, GOOGLE_MAPS_API_KEY } from '@env';

// Use in your code
console.log('API URL:', API_BASE_URL);

if (ENABLE_DEBUG_MODE === 'true') {
  console.log('Debug mode enabled');
}

// Initialize map with API key
const mapConfig = {
  apiKey: GOOGLE_MAPS_API_KEY,
};
```

## Available Environments

### Development (.env.development)

- Local development with debug features enabled
- Points to localhost API
- Verbose logging

### Staging (.env.staging)

- Pre-production testing environment
- Points to staging API servers
- Analytics enabled for testing

### Production (.env.production)

- Live production environment
- Points to production API servers
- Error-level logging only
- All debug features disabled

## Switching Environments

### During Development

The default `.env` file is used for local development.

### Building for Staging

```bash
# Copy staging config
cp .env.staging .env

# Build the app
npm run android
# or
npm run ios
```

### Building for Production

```bash
# Copy production config
cp .env.production .env

# Build release
npm run android --variant=release
# or
npm run ios --configuration Release
```

## Environment Variables Reference

| Variable              | Description                  | Example                                |
| --------------------- | ---------------------------- | -------------------------------------- |
| `ENV`                 | Current environment          | `development`, `staging`, `production` |
| `API_BASE_URL`        | Base URL for API requests    | `https://api.forseti.life/api`         |
| `API_TIMEOUT`         | API request timeout (ms)     | `10000`                                |
| `ENABLE_DEBUG_MODE`   | Enable debug features        | `true`, `false`                        |
| `ENABLE_MOCK_DATA`    | Use mock data instead of API | `true`, `false`                        |
| `ENABLE_ANALYTICS`    | Enable analytics tracking    | `true`, `false`                        |
| `GOOGLE_MAPS_API_KEY` | Google Maps API key          | `AIza...`                              |
| `DEFAULT_LATITUDE`    | Default map latitude         | `37.7749`                              |
| `DEFAULT_LONGITUDE`   | Default map longitude        | `-122.4194`                            |
| `AUTH_DOMAIN`         | Authentication domain        | `auth.forseti.life`                    |
| `AUTH_CLIENT_ID`      | OAuth client ID              | `your_client_id`                       |
| `LOG_LEVEL`           | Logging verbosity            | `debug`, `info`, `warn`, `error`       |

## TypeScript Support

Type definitions are provided in `src/types/env.d.ts`. VS Code will provide autocomplete and type checking for environment variables.

## Best Practices

1. **Never commit real credentials**: Keep `.env` files with actual keys out of version control
2. **Use example files**: Maintain `.env.example` with placeholder values
3. **Document new variables**: Update this README when adding new environment variables
4. **Validate on startup**: Consider adding runtime validation for required env vars
5. **Rotate keys regularly**: Especially for production environments

## Troubleshooting

### Environment variables not updating

1. Clear Metro bundler cache:

   ```bash
   npm start -- --reset-cache
   ```

2. Rebuild the app (environment variables are bundled at build time)

### TypeScript errors

If TypeScript doesn't recognize `@env`, ensure:

1. `src/types/env.d.ts` exists
2. The file is included in `tsconfig.json`
3. Restart TypeScript server in VS Code

### Babel errors

Verify `babel.config.js` includes the `module:react-native-dotenv` plugin configuration.
