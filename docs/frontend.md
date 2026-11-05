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
