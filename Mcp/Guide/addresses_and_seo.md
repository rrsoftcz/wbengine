# Addresses and search engine fields

Address (link of the page)
- Lowercase letters, digits and hyphens only; diacritics are removed (Příliš žluťoučký becomes prilis-zlutoucky). plan_article_page does this.
- Short and readable: the words that matter from the title, at most 50 characters, whole words, no apostrophes (humanity's becomes humanitys), no dates, no ids, never ending in a function word such as and, the, of.
- Under /blog/ for blog posts when the site has a blog page, otherwise at the top level. Always with the trailing slash.
- Unique. plan_article_page adds -2, -3 when an address is taken.
- Never change the address of a visible page casually: the site has no redirects, so links, bookmarks and search results to the old address break. update_page asks for confirm_link_change; explain the consequence to the user first. Fix the address before publishing, not after.

Browser title (html_title)
- At most 60 characters, the subject first, unique among the pages.
- Do not add the site name: the template appends it.

Description (html_description)
- 120 to 160 characters, one or two plain sentences that tell a reader what they get. No Markdown, no quotation of the first line.

Keywords (html_keywords)
- Optional and of little value today. A handful of words or nothing.

Article text
- The title is shown by the template. Start sections in the body at the second heading level (##) unless the preview shows otherwise.
- Every image needs alt text in the language of the article.
- Write translations as real translations, with their own title and introduction; the page can carry translated browser titles and descriptions through update_page.

check_article reports missing or too long fields. update_page changes them.
