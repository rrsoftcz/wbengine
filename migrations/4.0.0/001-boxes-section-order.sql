-- Optional performance migration. Check existing equivalent indexes first.
-- Replace cms_boxes if the installation uses a different table prefix.
-- Rollback: ALTER TABLE cms_boxes DROP INDEX wb_boxes_section_order;
ALTER TABLE cms_boxes ADD INDEX wb_boxes_section_order (section_id, `order`);
