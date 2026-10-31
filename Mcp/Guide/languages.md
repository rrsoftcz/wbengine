# Languages

site_info lists the languages of the site and the main one. Everything has a main-language text; other languages are translations.

- Articles: save_article_draft takes translations per locale (cs, en, en_US) with their own title, introtext and bodytext. A translation replaces the fields given for that locale. Write each one as a real translation, not a copy.
- Pages: update_page takes translations with title, html_title and html_description per locale.
- Menu: set_menu_entry takes translations with name per locale.
- check_article warns when a site language has no article translation. Visitors in that language then see the main language.
- The main-language text is what a visitor without a preference sees. Write it first, translate afterwards.
- Keep the same structure and facts in every language. Do not leave untranslated placeholders.
