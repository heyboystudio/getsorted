# Trades

Trades are **data, not code**. Each trade has a small YAML file here. A seeder loads these into the `trades` table; admins edit them in the admin panel afterwards (the seeder only adds trades whose key is new and never overwrites edits).

There are no sub-services or scoping questions (spec 020). Siya reads the customer's own words and extracts short facts ("tap drips when closed", "DB trips when the light is on"). Those facts are highlighted on the job so each pro can decide whether they can help.

## Format

```yaml
trade: plumbing            # stable key, never renamed
name: Plumbing             # display name
status: demo               # demo | live
registration: pirb         # optional: pirb | electrical_registered_person. A pro can verify it for a badge; it never blocks matching.
safety_advice:             # optional: short reviewed lines shown for urgent jobs. Siya never writes safety instructions itself.
  - If water is flooding, close the main stopcock first.
```

## How the AI assistant uses this

- It receives the list of active trades (key and name) and picks one for the job with `set_trade`.
- It records facts with `add_job_fact`, each backed by an exact quote from the customer's message.
- Customer text is **untrusted input**: it is passed to the model as data, never as instructions, and every tool call is validated by the application before anything is saved.
