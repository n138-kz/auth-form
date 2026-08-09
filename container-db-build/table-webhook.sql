DROP TABLE IF EXISTS webhook_discord;
-- TABLE
CREATE TABLE IF NOT EXISTS webhook_discord (
    id SERIAL PRIMARY KEY
    ,created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    ,updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    ,m_id                   VARCHAR(255)
    ,m_channel_id           VARCHAR(255)
    ,m_author_id            VARCHAR(255)
    ,m_author_username      VARCHAR(255)
    ,m_author_bot           BOOLEAN DEFAULT FALSE
    ,m_author_global_name   VARCHAR(255)
    ,m_pinned               BOOLEAN DEFAULT FALSE
    ,m_mention_everyone     BOOLEAN DEFAULT FALSE
    ,m_webhook_id           VARCHAR(255)
    ,m_response             TEXT
);
