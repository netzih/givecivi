### Changelog

#### Version 0.4.0
- [X] Pass actual payment methods from GiveWP to CiviCRM (instead of "See Give")
- [X] Fixed critical bug in subscription fetching logic (assignment vs comparison)
- [X] Added explicit hooks for recurring donation renewal payments
- [X] Improved logging with timestamps and better error handling
- [X] Modernized PHP code with null coalescing operators
- [X] Added support for multiple payment gateways (Stripe, PayPal, etc.)

### To-Dos

- [X] Licensing (Easy Digital Downloads)
- [X] Workaround 300s PHP max (i.e., eliminate need for install warning)
- [X] Do more diligent search for existing contacts (i.e. cf. name + email)
- [X] Improve logging, errors and user feedback
- [X] Pass actual payment methods instead of "See Give"
- [X] Ensure recurring donations sync properly


### Future Features?

- [ ] Capture billing address
- [ ] Capture notes
- [ ] Add admin settings page for payment method mappings
