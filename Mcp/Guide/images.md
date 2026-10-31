# Images

- upload_image takes a PNG, JPEG or WebP of at most 512 KiB and at most 8192 pixels on a side. Give path (a file on this computer) or base64 with mime, and alt text.
- The image is public as soon as it is uploaded. Do not upload anything the user did not choose.
- The result contains the address and the Markdown to paste into the article, for example ![A loaf of bread](/media/abc). Put it on its own line between paragraphs.
- alt describes what the picture shows, in the language of the article. Never leave it empty and never write "image of".
- If the file is too large, ask the user to resize it or compress it; this server does not edit images.
- list_images shows what was uploaded. Images cannot be deleted through this server.
- If the site answers that it cannot store images, the administrator has to configure the image directory.
