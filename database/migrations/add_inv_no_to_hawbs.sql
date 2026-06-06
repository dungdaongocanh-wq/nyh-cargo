ALTER TABLE `hawbs`
  ADD COLUMN `inv_no` VARCHAR(200) NOT NULL DEFAULT ''
  AFTER `notify_party`;
