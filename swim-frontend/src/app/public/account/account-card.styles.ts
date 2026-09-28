/** Shared look of the small public account pages (e-mail confirmation, password reset). */
export const ACCOUNT_CARD_STYLES = `
  .acc-page   { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--swim-dark); padding: 1rem; }
  .acc-card   { width: 400px; max-width: 100%; background: var(--swim-card) !important; }
  .acc-card ::ng-deep .p-card-title { color: var(--swim-gold); text-align: center; }
  .acc-form   { display: flex; flex-direction: column; gap: 1rem; }
  .field      { display: flex; flex-direction: column; gap: .4rem; }
  .field label { font-size: .85rem; color: var(--swim-muted); }
  .field input, .field ::ng-deep .p-password, .field ::ng-deep .p-password input { width: 100%; }
  .intro      { margin: 0 0 1rem; font-size: .85rem; line-height: 1.5; color: var(--swim-muted); }
  .err        { color: #f44336; font-size: .78rem; }
  .done       { text-align: center; }
  .done .pi   { font-size: 2.5rem; color: var(--swim-gold); }
  .done p     { color: var(--swim-muted); font-size: .9rem; line-height: 1.5; }
  .links      { margin-top: 1.25rem; text-align: center; font-size: .85rem; }
  .links a    { color: var(--swim-gold); text-decoration: none; }
`;

/** Tokens in e-mail links are 64 hex characters (bin2hex(random_bytes(32)) in includes/user_repo.php). */
export const EMAIL_TOKEN_RE = /^[0-9a-f]{64}$/;

export const INCOMPLETE_LINK_MSG =
  'Link jest niekompletny — prawdopodobnie został ucięty przy kopiowaniu. Otwórz go z maila jeszcze raz, w całości.';
