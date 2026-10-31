# How a WBengine site works

Read this first. It is short on purpose.

- Page: an address such as /blog/my-post/ with a title, a browser title (html_title) and a description (html_description). Find pages with list_pages.
- Article: Markdown text with a title and an introduction, in the main language and optionally translated. An article belongs to exactly one page. It has a live version and a draft: save_article_draft changes the draft, publish_article makes it live.
- Box: a unit of content placed on a page, in a section. A page shows only what its boxes show. A box of an article type shows the article of its page, so a page with no such box stays empty even when its article is published. list_box_types shows which types exist and which show articles.
- Section: a region of the layout (central, header). Boxes live in sections. A section that is not active is not displayed.
- Menu: the entries of the main menu, each pointing to a page. Most skeletons display only top level entries.
- Languages: site_info lists them. Texts exist in the main language and as translations. A visitor sees the translation of the language they use, or the main language when there is none.
- Hidden: a published article can be hidden with hide_article. Its address then answers 404 and its menu entry disappears until unhide_article. Nothing is deleted.
- Change log: every change is recorded with the actor. list_changes shows it.

What this server never does: delete pages, articles, boxes, images or menu entries; change users, settings or templates; create new box types (they are code written by the site's developer).

How to start: call site_info (it says what this key may do and whether new article pages can be created), then wbengine_guide with publish_article for the steps of publishing a new article.

The user is probably not technical. Do the technical steps yourself, say in plain words what you did, and ask only for decisions that are theirs: the text, the language, the title, whether to publish.
