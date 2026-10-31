# When something does not work

Errors come as plain messages with a remedy. The common ones:

| Message or situation | What to do |
|---|---|
| key was rejected (unauthorized) | The key is wrong, revoked or expired. Ask the administrator for a new one. |
| the key does not allow this (forbidden) | The key lacks a permission group. The administrator adds it on the Integrations page. Say which group: Content, Publishing, Structure, Images, History or Analytics. |
| article changed since it was read (precondition_failed) | Someone else changed it. Call get_article, merge the change, retry. |
| that page already has an article | A page holds one article. Choose another page or create one with new_page. |
| article has no page (missing_site) | Give page_id or new_page when saving the draft. |
| no draft to publish | Save a draft first. |
| cannot hide articles yet (hide_unavailable) | The site needs its database update. Tell the administrator. |
| a page with that link exists / link not valid | Choose another address; see addresses_and_seo. |
| site could not be reached (transport) | The address or the network is wrong. Check WBENGINE_MCP_URL. |
| rate limited | Wait a minute. |

Published, but the page is empty: the page has no box that shows articles. check_article says so; add one with add_box (see boxes) or use a page that shows articles.

Published, but not in the menu: the entry is hidden or nested. list_menu, then set_menu_entry. Most skeletons show only top level entries.

New page does not appear in search: wait; search engines need time, and the description and the browser title must be filled in.

Image upload fails: too large (512 KiB), wrong type, or the site has no image directory.

When a tool says the key cannot do something, do not try another route around it. Tell the user what permission is missing and who can add it.
