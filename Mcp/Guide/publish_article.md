# Publishing a new article, step by step

1. Ask what the article is about, who reads it and in which languages it is needed. Do not invent facts; ask for what is missing.
2. Call site_info. If "New article pages" says no, read why: either write into a page that already shows articles (list_pages marks them), or tell the user what the administrator must do.
3. Call plan_article_page with the title (and the introduction). It proposes the address, the browser title, the description, the box and a menu name. Show the plan in plain words and let the user change it.
4. Pictures: upload_image for each (alt text required), paste the returned Markdown into the text.
5. Call save_article_draft with the title, introtext, bodytext and translations for every other site language, new_page from the plan and menu_name when the user wants a menu entry. This creates the page (not visible yet), the box that shows the article and the menu entry.
6. Call preview_article for each language and read it. Show the user what it says.
7. Call check_article. Fix blockers. Warnings and advice (missing description, missing translation of the article, the page or the menu entry) are worth fixing: update_page takes translations for the browser title and description, set_menu_entry for the menu name. Tell the user when you leave one.
8. Ask the user explicitly whether to publish. Publish only after a clear yes.
9. Call publish_article. It checks the page again, makes the page and the menu entry visible and says what is still open. Tell the user the address.
10. If the user wants it gone again: hide_article. It is reversible with unhide_article.

An article that already exists: get_article, change the draft with save_article_draft (pass the id), preview, check, ask, publish_article. Writing into an existing page: pass its page_id; a page holds one article.

Publish, hide and unhide only when the user asked. When in doubt save a draft and say so.
