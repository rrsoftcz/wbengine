# Boxes

A box puts content on a page. The type of a box decides what it does, and the types are registered by the site's developer: list_box_types shows them. You can add a box of a registered type, change it, and take it off a page. You cannot create a new type or choose code to run.

- add_box(name, type, section, page_ids, order): adds the box. A box of the article type is what makes a page show its article. Section is the key (central) or the id from list_sections; a type may be limited to some sections.
- list_boxes(page_id): what a page shows now. Without page_id every box is listed.
- update_box: name, order (smaller shows first within the section), the list of pages (replaces it) and the device limit. device_min 1 shows the box on every device, 2 on tablets and larger, 3 on desktop only; device_strict shows it on exactly that class.
- hide_box(id, page_id): takes the box off one page and keeps it. The result names the pages before. show_box puts it back. This is the way to hide a box; nothing is deleted.
- A shared box is shown on every page; hide_box refuses it. Use update_box to limit it.

Facts
- A page whose boxes show no article stays empty even when the article is published. check_article says so before publishing.
- A box in a section that is not active is not displayed. list_sections shows which are active.
- Order is a number from 0 to 255 per section. Equal numbers are shown in an unspecified order, so give boxes distinct orders.
- Legacy boxes written in code (login, documentation pages, jumbotron) appear in list_boxes without a type. Do not move them unless asked.
