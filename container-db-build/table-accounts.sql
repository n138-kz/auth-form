DROP VIEW IF EXISTS accounts_view_unsafe;
DROP VIEW IF EXISTS accounts_view;
DROP TABLE IF EXISTS accounts_otp;
DROP TABLE IF EXISTS accounts;
-- TABLE
CREATE TABLE accounts (
    id SERIAL PRIMARY KEY,
    userid VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE accounts_otp (
    account_id INT PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE,
    otp_secret VARCHAR(255) NOT NULL,       -- TOTP等の秘密鍵（暗号化して保持）
    is_enabled BOOLEAN DEFAULT FALSE,       -- 二段階認証が有効かどうか
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
-- VIEW
CREATE VIEW accounts_view_unsafe AS
    SELECT
        a.id,
        a.userid,
        a.password_hash,
        COALESCE(o.is_enabled, FALSE) AS is_otp_enabled, -- OTP設定がない場合は FALSE
        o.otp_secret,
        a.created_at,
        a.updated_at
    FROM
    accounts a
LEFT JOIN 
    accounts_otp o ON a.id = o.account_id;
CREATE VIEW accounts_view AS
    SELECT
        a.id,
        a.userid,
        COALESCE(o.is_enabled, FALSE) AS is_otp_enabled, -- OTP設定がない場合は FALSE
        a.created_at,
        a.updated_at
    FROM
    accounts a
LEFT JOIN 
    accounts_otp o ON a.id = o.account_id;
INSERT INTO accounts(userid, password_hash) VALUES ('admin@localhost', 'U2FsdGVkX188L2MufK+yMWuLKmSAtuWtnP+Q4MGyusU='); -- admin@localhost / password
