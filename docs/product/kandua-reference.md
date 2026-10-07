# Kandua reference: how Jess and booking work

Observed on 2026-10-05 at kandua.com (signed out) and marketplace.kandua.com (signed in as the founder). Nothing was posted. This is the flow Get Sorted copies (spec 017).

## The big idea

One assistant, one screen, one thread. Jess and the booking form are the **same thing**: the chat, the progress bar, the address picker, the calendar, the photo card and the summary all appear as cards inside one conversation. You never leave it, and nothing you've said is asked again.

## Entry points

| Where | What you see |
|---|---|
| Public home, "Book a Pro" | A modal: "Connect with vetted Pros", powered by Kandua AI. Eight trade tiles plus **Get guidance** ("Ask Jess anything…"). Restart and close in the header. |
| Signed-in home | Hero box "What's going on at home?" with a text field and chips (Blocked drain, No power, No hot water, General repair). Under it, "Quick booking": pick a trade, then its most-booked services. "Your active job" card on the right. |
| Signed-in sidebar | **Book a Pro** and **Ask Jess** buttons; Home, My Jobs, Explore (guides), Home Hub. |

## Book a Pro: signed out (3 steps)

Progress bar: **Describe your problem → Where and when → Create account and confirm**.

1. **Trade** tile tapped → green "You selected: Plumber" card.
2. **Describe** (step 1 of 3). Jess asks "What's the plumbing problem?" with chips (Leak repair, Blocked drain, Geyser issues, … Other) and a free-text box. Then **2 or 3 follow-up questions**, each with chips and free text accepted:
   - Geyser issues → "What's happening with your geyser?" (No hot water, Leaking, Noise, Too hot/cold, Tripping power, Not sure)
   - Leaking → "Where is the leak coming from?" (I typed my own answer; Jess understood it)
   - "Standard electric or solar?"
   Jess's replies are short and warm and add one line of context ("these can worsen quickly").
3. "Got it! I've got what I need… Any additional details or special instructions?" → **Continue** / **Provide additional details** (free text, no photos here).
4. **Where and when** (step 2 of 3): suburb search only (not the street), "Use my current location", "Enter it another way". Then a calendar: **one date, no time window**. Confirmed items collapse into green ticked cards.
5. **Create account and confirm** (step 3 of 3): "Top-rated Pros found near you ★4.5+" → tick terms → Continue → sign in with email, then name and number.

## Book a Pro: signed in (4 steps)

Progress bar: **Service → Describe → Where and when → Confirm**.

1. Trade tile → "What's the electrical problem?" chips (Installations, CoC, Light fixtures, Fault finding, Wiring, Back-up power, Appliances, Other).
2. Fault finding → one follow-up in Jess's words. I typed "kitchen plugs stopped working and the breaker trips when I reset it" → Jess gave **two safety tips inline** (don't keep resetting, unplug appliances) and said it had enough. Two questions total.
3. Continue / Provide additional details ("mornings are best, there's a dog" → "Got it, I've noted…").
4. **Where** (step 3 of 4): list of **saved properties** + "Add another property". Picking one immediately runs "Looking for nearby Pros…".
   - **Not covered:** "Unfortunately we don't have providers for Electrical repairs & fault finding in your location. Want us to let you know?" → Yes, keep me updated / No thanks / Request a different service.
   - **Covered:** "GREAT NEWS · We found top-rated Pros near you!"
5. **When:** calendar, one date → "Date confirmed" card.
6. "Almost there." **Photos** card: optional but recommended, up to 5, Upload from gallery / Take a photo / **Skip for now**.
7. **Booking summary**: Need help with · Address · Date · Phone number → **Confirm booking** / Change details. "By confirming, you agree to the Terms and allow us to share your job with vetted Pros."

## Get guidance (Ask Jess)

- Jess introduces herself ("your Kandua home companion… advice, cost guidance, or booking").
- Asked "breaker keeps tripping, how much would an electrician cost?" → a diagnosis line, **cost guidance** (fault finding ≈ R750 incl. call-out and first hour, ≈ R550/hour after, materials extra), "What affects the price", "the Pro will assess on-site and give a clear quote", then "Would you like to book an electrician?"
- "Yes" → straight to "Any additional details?" → location. **No scoping questions were re-asked**: the chat already had the problem.

## After posting

- Job card states: "Finding your pro…" → stages **Job posted → Quote → Deposit → Work → Final**.
- Job page: date, suburb, invoices with **Pay**, deposit, quote, **WhatsApp** button, **Cancel job**.
- Marketing promise: "a top-rated expert matched to your needs, not a dozen pros competing for your phone number"; pay only through the Pay Now button.

## What Get Sorted does today (same day, dev site)

| # | Get Sorted today | Kandua |
|---|---|---|
| 1 | Siya chat is its own page; "Continue booking" **leaves the chat** for an 8-step wizard | Everything happens in one chat thread |
| 2 | Siya asks "Is this correct?" in its bubble **and** in the confirm card | One confirmation, as a tick card |
| 3 | First message ("geyser leaking from the bottom") is ignored: Siya asks "What's happening?" again | Free text is understood and answered questions are skipped |
| 4 | Wizard counter jumps "Step 1 of 8" → "Step 4 of 8" | Fixed 3–4 step bar that matches what you see |
| 5 | "Anything else pros should know?" re-asked, pre-filled with the chat text | Asked once, inside the chat |
| 6 | Location asked **twice**: suburb (step 1) then property (step 6), then "This property is in Memorial Park. Check coverage there instead?" | Asked once: pick a property (or suburb when signed out), coverage runs immediately |
| 7 | Date **and** morning/afternoon/flexible window | Date only |
| 8 | Siya may not mention prices at all | Jess gives labour cost guidance with a "Pro will quote on-site" caveat |
