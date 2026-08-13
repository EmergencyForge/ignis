# Third-party notices and UI source policy

Ignis is distributed under the GNU General Public License v3.0 unless a file or dependency states otherwise. The project also uses the third-party assets listed in the README and their respective licenses.

## Tailwind Plus

Tailwind Plus was used by an authorized license holder as a visual and interaction-design reference while developing parts of the Ignis application UI. The repository is intended to contain project-native PHP markup and SCSS compositions only; the original Tailwind Plus download, package, templates, and component source are not included.

The shared `twplus-*` class prefix identifies Ignis compositions inspired by common application-UI patterns. It does not identify an embedded Tailwind Plus package and does not grant access to Tailwind Plus source files.

Contributor rules:

- Keep licensed Tailwind Plus source files outside this repository.
- Do not commit complete or substantially unchanged Tailwind Plus component markup, examples, assets, or packages.
- Implement designs with Ignis tokens, Font Awesome icons, existing behavior, and project-native markup.
- Do not turn the resulting work into a standalone UI kit, template collection, page builder, or component library based on Tailwind Plus.
- Only people covered by the applicable Tailwind Plus Personal or Team license may access the original files used as references.
- If a future change needs copied or materially derivative Tailwind Plus source, review its licensing separately before committing it. Such source must not be assumed to be covered by the project GPL.

The current Tailwind Plus license and FAQ are available at <https://tailwindcss.com/plus/license>. This notice documents the project's intended integration model; it is not legal advice.

## Icon system

Ignis continues to use Font Awesome Free as its single application icon system. Heroicons and Lucide are not bundled, avoiding a second icon dependency and inconsistent visual language.
