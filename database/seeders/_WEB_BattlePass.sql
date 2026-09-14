DROP TABLE IF EXISTS [dbo].[WEB_BattlePass]

CREATE TABLE [dbo].[WEB_BattlePass](
    [ID] [int] IDENTITY(1,1) NOT NULL,
    [CharID] [int] NOT NULL,
    [IsPremium] [bit] NOT NULL CONSTRAINT [DF_WEB_BattlePass_IsPremium] DEFAULT ((0)),
    [Points] [int] NOT NULL,
    [ClaimedItems] [varchar](max) NOT NULL
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
