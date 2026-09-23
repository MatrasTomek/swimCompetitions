// Dev-server proxy for the PHP API. The target comes from API_TARGET in
// swim-frontend/.env (gitignored, see .env.example) so the production domain
// is not committed; without it the local PHP server on 127.0.0.1:8000 is used.
try {
  process.loadEnvFile(__dirname + '/.env');
} catch {
  // no .env — fall back to the local default
}

module.exports = {
  '/api/v1/index.php': {
    target: process.env.API_TARGET || 'http://127.0.0.1:8000',
    secure: false,
    changeOrigin: true,
    logLevel: 'debug',
  },
};
