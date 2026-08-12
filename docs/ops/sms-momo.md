# SMS & Mobile Money

## SMS (alertes critiques)

Variables (`.env`) :

```env
SMS_ENABLED=true
SMS_DRIVER=auto          # log | orange | airtel | auto
SMS_ORANGE_URL=https://sms-api.example/orange/send
SMS_ORANGE_TOKEN=...
SMS_ORANGE_SENDER=Manolya
SMS_AIRTEL_URL=https://sms-api.example/airtel/send
SMS_AIRTEL_TOKEN=...
SMS_AIRTEL_SENDER=Manolya
```

- `log` : écrit dans les logs (défaut / local)
- `orange` / `airtel` : POST JSON `{ to, message, sender }` avec Bearer token
- `auto` : préfixe Congo (Orange 80/81/84/85/89, Airtel 97/98/99)

Les alertes `critical` / `high` notifient les owners par **e-mail + SMS** si `SMS_ENABLED=true` et `users.phone` renseigné.

## Mobile Money

Sans credentials, les adapters **retombent sur le stub** (comportement actuel POS).

```env
MOMO_DEFAULT_PROVIDER=stub
MOMO_ORANGE_TOKEN=
MOMO_ORANGE_CHARGE_URL=
MOMO_ORANGE_REFUND_URL=
MOMO_AIRTEL_TOKEN=
MOMO_AIRTEL_CHARGE_URL=
MOMO_AIRTEL_REFUND_URL=
MOMO_MTN_TOKEN=
MOMO_MTN_CHARGE_URL=
MOMO_MTN_REFUND_URL=
```

Caisse : paiement Mobile Money → choix opérateur Orange / Airtel / MTN.

Contrat HTTP charge attendu : `POST charge_url` → JSON `{ status, transaction_id }` ; refund similaire.
