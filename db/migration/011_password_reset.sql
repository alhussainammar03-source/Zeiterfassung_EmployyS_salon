ALTER TABLE employees
    ADD COLUMN reset_token VARCHAR(255) NULL,
    ADD COLUMN reset_token_ablauf DATETIME NULL;

ALTER TABLE `user`
    ADD COLUMN reset_token VARCHAR(255) NULL,
    ADD COLUMN reset_token_ablauf DATETIME NULL;
