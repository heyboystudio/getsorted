# Browser test of the core path

`core-path.mjs` drives a real browser (Brave by default) through: client books a job with Siya, pro accepts and sends an estimate, client chooses the pro (the introduction), pro sees the contact details, client marks the job done and reviews, pro replies.

```
E2E_BASE_URL=https://usesorted.co.za \
E2E_CUSTOMER=test.customer@usesorted.co.za E2E_PRO=test.pro@usesorted.co.za E2E_PASSWORD=… \
npm run e2e
```

- Run it against the test site or a local server, **never production** (it creates a real job and a review).
- Needs a client account and an approved pro who offers plumbing within range of 100 Musgrave Road, Durban.
- It uses the real AI and Google address lookup, so it takes about 30 seconds and needs the test site's keys.
- If the introduction fee is on and the pro has no credit, it stops at "pro sends an estimate" with a message saying so (this also proves the top-up popup appears). Top the pro up, or switch the fee off, then rerun.
- `E2E_HEADED=1` shows the browser; `E2E_BROWSER` points at another Chromium-based browser.
- It is not part of `composer check` because it needs a running site and accounts.
