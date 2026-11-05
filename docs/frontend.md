# Frontend

Pages are server-rendered with Twig. Assets are served by AssetMapper, so there is no Node build.

- **Tailwind CSS** (via `symfonycasts/tailwind-bundle`) builds `assets/styles/app.css`. The
  `tailwind` service watches for changes, and `make deps` does a one-off build.
- **Alpine.js** adds interactive behaviour, and is loaded through the importmap. Components are
  registered in `assets/app.js` and live in `assets/ui/`.
- **Design tokens**: Tailwind's default theme gives the primitives. The semantic tokens in
  `assets/styles/app.css` (`bg-surface`, `text-fg-muted`, `bg-accent`...) are what templates use.
  Browse them at <http://localhost:8080/design-tokens>.
- **Components**: reusable UI is Twig Components in `templates/components/` (`<twig:Button>`,
  `<twig:Input>`...), styled with utilities in their markup.
- **Forms** render through `templates/form/theme.html.twig`, so `form_row()` matches the components.
- **Mockups**: the agreed designs, with fake data and every state, are at
  <http://localhost:8080/mockups>.

## Applications list

The backoffice list is the one page rendered in the browser. Twig renders the shell and the filters
form (never submitted); `assets/ui/applications_list.js` reads the fields, queries
[`GET /api/applications`](api.md) and renders the rows, pagination and states.

- The element carries `data-api-url` and `data-detail-url` (with `__id__` for the application id).
- Search and min score wait 300 ms; the other fields apply at once. Filters and page are mirrored in
  the URL: filter changes replace the history entry, pagination pushes one.
- A 422 lists the invalid filters, marks the fields invalid and keeps the last results.
- Badges, relative times and pagination reproduce `StatusBadge`, `ScoreBadge`, the `time_ago` filter
  and `Pagination`: keep them in step when changing those.
- Auto-refresh re-sends the query of the results on screen every 10 s while the tab is visible. The
  choice is kept in `localStorage` (`applications.autoRefresh`).
