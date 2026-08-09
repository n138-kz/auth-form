DROP VIEW IF EXISTS accounts_view_unsafe;
DROP VIEW IF EXISTS accounts_view;
DROP TABLE IF EXISTS accounts_otp;
DROP TABLE IF EXISTS accounts;
-- FUNCTION
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';
-- TABLE
CREATE TABLE IF NOT EXISTS accounts (
    id SERIAL PRIMARY KEY,
    userid VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS accounts_otp (
    account_id INT PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE,
    otp_secret VARCHAR(255) NOT NULL,  -- TOTP等の秘密鍵（暗号化して保持）
    is_enabled BOOLEAN DEFAULT FALSE,  -- 二段階認証が有効かどうか
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS accounts_attr (
    account_id INT PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE,
    mail_authrized BOOLEAN DEFAULT FALSE,   -- メール認証済みかどうか
    account_enabled BOOLEAN DEFAULT TRUE,   -- アカウント使用可能
    account_deleted BOOLEAN DEFAULT FALSE,  -- アカウント削除済み
    account_restrictions_begin TIMESTAMP WITH TIME ZONE DEFAULT NULL, -- アカウントログイン制限期間(自)
    account_restrictions_until TIMESTAMP WITH TIME ZONE DEFAULT NULL, -- アカウントログイン制限期間(至)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS accounts_attr_thirdparty_accounts (
    account_id INT REFERENCES accounts(id) ON DELETE CASCADE,
    media_type VARCHAR(63) NOT NULL,  -- ログイン先(Google, Discord, Github, etc)
    tp_account_id VARCHAR(63) NOT NULL,
    tp_account_name VARCHAR(63) NOT NULL,
    tp_attr_json JSONB NOT NULL DEFAULT '{"id": null}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id, media_type)
);
-- TRIGGER
CREATE OR REPLACE TRIGGER update_accounts_updated_at
    BEFORE UPDATE ON accounts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
CREATE OR REPLACE TRIGGER update_accounts_otp_updated_at
    BEFORE UPDATE ON accounts_otp
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
CREATE OR REPLACE TRIGGER update_accounts_attr_updated_at
    BEFORE UPDATE ON accounts_attr
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
CREATE OR REPLACE TRIGGER update_accounts_attr_thirdparty_accounts_updated_at
    BEFORE UPDATE ON accounts_attr_thirdparty_accounts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
-- VIEW
CREATE OR REPLACE VIEW accounts_view_unsafe AS
    SELECT
        a.id,
        a.userid,
        a.password_hash,
        COALESCE(o.is_enabled, FALSE) AS is_otp_enabled, -- OTP設定がない場合は FALSE
        o.otp_secret,
        COALESCE(attr.mail_authrized, FALSE) AS mail_authrized,
        COALESCE(attr.account_enabled, TRUE) AS account_enabled,
        COALESCE(attr.account_deleted, FALSE) AS account_deleted,
        attr.account_restrictions_begin,
        attr.account_restrictions_until,
        a.created_at,
        a.updated_at
    FROM
        accounts a
    LEFT JOIN 
        accounts_otp o ON a.id = o.account_id
    LEFT JOIN
        accounts_attr attr ON a.id = attr.account_id;
CREATE OR REPLACE VIEW accounts_view AS
    SELECT
        a.id,
        a.userid,
        COALESCE(o.is_enabled, FALSE) AS is_otp_enabled, -- OTP設定がない場合は FALSE
        COALESCE(attr.mail_authrized, FALSE) AS mail_authrized,
        COALESCE(attr.account_enabled, TRUE) AS account_enabled,
        COALESCE(attr.account_deleted, FALSE) AS account_deleted,
        attr.account_restrictions_begin,
        attr.account_restrictions_until,
        a.created_at,
        a.updated_at
    FROM
        accounts a
    LEFT JOIN 
        accounts_otp o ON a.id = o.account_id
    LEFT JOIN
        accounts_attr attr ON a.id = attr.account_id;
CREATE OR REPLACE VIEW accounts_view_candidate AS
    SELECT
        a.id,
        a.userid
    FROM
        accounts a
    LEFT JOIN
        accounts_attr attr ON a.id = attr.account_id
    WHERE
        (
            attr.mail_authrized = false
            OR
            attr.mail_authrized IS NULL
        )
        AND
        a.created_at <= CURRENT_TIMESTAMP - INTERVAL '6 hours';

INSERT INTO accounts(userid, password_hash)
    VALUES ('admin@localhost', 'U2FsdGVkX188L2MufK+yMWuLKmSAtuWtnP+Q4MGyusU='); -- admin@localhost / password
