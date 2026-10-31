# Menu

The main menu is a list of entries, each pointing to a page.

- list_menu shows the entries sorted by level and order, with their page, parent and visibility.
- set_menu_entry adds an entry (page_id and name) or changes one (id). order is the position among the entries of the same level: the entries from that position on move one place down, so no two entries clash. Without order a new entry goes last.
- parent is the menu id of the entry above, 0 for the top level. Most skeletons display only top level entries, so a nested entry may not appear in the header: look at the site after changing it.
- To remove an entry from the menu set visible to false. The entry is kept and can be shown again. Nothing is deleted.
- A new entry for a page that is not visible yet is created hidden; publish_article shows it together with the page.
- name is shown in the main language. Add translations per locale as {"name", "description"} so visitors in another language see their own words.
- Keep names short (a word or two), in the order a visitor expects.
