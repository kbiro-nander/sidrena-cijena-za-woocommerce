#!/usr/bin/env bash
# Generates languages/sidrena-cijena-za-woocommerce.pot from PHP sources (needs gettext's xgettext).
set -euo pipefail
cd "$(dirname "$0")/.."
OUT=languages/sidrena-cijena-za-woocommerce.pot
FILES=$(find . -name '*.php' -not -path './vendor/*' -not -path './tests/*' -not -path './node_modules/*' | sort)
# shellcheck disable=SC2086
xgettext --language=PHP --from-code=UTF-8 --add-comments=translators \
  --keyword=__:1 --keyword=_e:1 --keyword=esc_html__:1 --keyword=esc_html_e:1 --keyword=esc_attr__:1 --keyword=esc_attr_e:1 \
  --keyword=_x:1,2c --keyword=_ex:1,2c --keyword=esc_html_x:1,2c --keyword=esc_attr_x:1,2c \
  --keyword=_n:1,2 --keyword=_nx:1,2,4c --keyword=_n_noop:1,2 --keyword=_nx_noop:1,2,3c \
  --package-name="Sidrena cijena za WooCommerce" --package-version="$(grep -m1 'Version:' sidrena-cijena-za-woocommerce.php | sed 's/.*Version: *//')" \
  --msgid-bugs-address="https://github.com/kbiro-nander/sidrena-cijena-za-woocommerce/issues" \
  -o "$OUT" $FILES
sed -i.bak 's/charset=CHARSET/charset=UTF-8/; s/^"Language: \\n"/"Language: hr_HR\\n"/' "$OUT" && rm -f "$OUT.bak"
echo "Wrote $OUT ($(grep -c '^msgid' "$OUT") strings)"
