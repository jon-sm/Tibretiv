# Product

## Pages

| Page | URL | For |
| --- | --- | --- |
| Open positions | <http://localhost:8080/> | Candidates pick a role |
| Apply | `/positions/{id}` | Candidate data, plus the CV pasted as plain text |
| Application received | `/application-received` | One-time confirmation after applying |
| Applications | <http://localhost:8080/backoffice> | Recruiters search and filter, newest first |
| Application detail | `/backoffice/{id}` | CV, AI summary and score, status, timestamps |

### Applications list

- Search by name, surname or email (case-insensitive; each word must match one of them).
- Filters: position, status, enrichment (processed / pending / failed), minimum score, applied
  from / to. Results update as you type, and filters and page live in the URL, so a view can be
  bookmarked and the back button works.
- 20 per page, newest first.
- An auto-refresh toggle re-queries the list every 10 seconds while the tab is visible. It is on by
  default and remembered per browser; its tooltip shows when the list was last refreshed.

The list is loaded in the browser from the [applications API](api.md), so the page needs JavaScript.

## AI enrichment

After an application is submitted, an enrichment runs in the background worker: it summarises the
CV and scores its relevance (0–100) for the position. The list and detail show "Analysing…" until it
finishes. The detail page reloads by itself when it does; the list picks it up on its next refresh.

A failed attempt is retried 3 times (after 1 s, 2 s and 4 s). After the last one, the enrichment is
marked failed and the message is kept in the failure transport.

## Demo notes

- The LLM is mocked: no API is called. Until the scoring criteria are agreed, it returns the start
  of the CV (up to 280 characters) as the summary and a random score from 0 to 100, after a
  simulated delay (`MOCK_LLM_LATENCY_MS`, 1.5 s by default).
- A CV containing `FAIL_ENRICHMENT` makes every attempt fail, to show the retry and failed path.
- One application per email per position. Gmail dots and `+tags` count as the same mailbox.
- The backoffice has no login in this demo.
- Dates in the backoffice are shown and filtered in Europe/Madrid time.

## Known limitations

- Events are published right after saving, without an outbox: if Redis is down at that moment, the
  application is saved but its enrichment is not started.
- A failed enrichment cannot be retried from the UI yet.
