# Scoping trees

Scoping is **data, not code**. Each trade has a YAML file here describing its services and the questions asked for each. A seeder loads these into the database (`trades`, `services`, `scoping_questions`); admins edit them in the admin panel afterwards. **The services and questions below are demo content** and will change.

## Format

```yaml
trade: plumbing            # stable key, never renamed
name: Plumbing             # display name
status: demo               # demo | live
services:
  - key: leak_repair       # stable key
    name: Leak repair
    description: Fix a visible leak from a tap, pipe, toilet or geyser fitting.
    requires_registration: null   # e.g. pirb, electrical_registered_person
    emergency_capable: true       # can be booked as "today"
    questions:
      - key: leak_location
        prompt: Where is the leak coming from?
        type: single_choice       # single_choice | multi_choice | yes_no | number | text
        options: [Tap, Pipe, Toilet, Geyser, Not sure]
        required: true
      - key: severity
        prompt: How bad is it?
        type: single_choice
        options: [Dripping, Steady flow, Flooding]
        flags:
          urgent_if: [Flooding]   # marks the job urgent and shows safety advice
    safety_advice:
      - If water is flooding, close the main stopcock before anything else.
```

Every service automatically ends with:
1. An optional free-text "anything else your pro should know?"
2. Optional photos (up to 5).

## How the AI assistant uses this

- **Free-text first ("describe your problem")**: the assistant reads the customer's words and returns **structured output** — `{trade_key, service_key, confidence, prefilled_answers, summary}` — validated against this data. If confidence is low it asks the customer to pick from the trade/service list instead of guessing.
- **Summary**: after the questions, the assistant writes a 2–3 sentence neutral summary for pros. It must not add facts the customer didn't give, must not quote prices, and must not give the customer instructions beyond the `safety_advice` lines.
- Customer text is **untrusted input**: it is passed to the model as data, never as instructions, and the model's output is validated before saving.
