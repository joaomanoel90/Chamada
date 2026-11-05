

CREATE TABLE [dbo].[TB_USUARIOS] (
  [ID] int  IDENTITY(1,1) NOT NULL,
  [NOME] nvarchar(255) COLLATE Latin1_General_CI_AS  NULL,
  [SENHA] nvarchar(255) COLLATE Latin1_General_CI_AS  NULL,
  [NIVEL] int  NULL,
  [CPF] nvarchar(11) COLLATE Latin1_General_CI_AS  NULL,
  [CREATED] datetime  NULL,
  [MODIFIED] datetime  NULL,
  [ATIVO] bit  NULL,
  [USUARIO] int  NULL
)
GO

ALTER TABLE [dbo].[TB_USUARIOS] SET (LOCK_ESCALATION = TABLE)
GO


-- ----------------------------
-- Records of TB_USUARIOS
-- ----------------------------
SET IDENTITY_INSERT [dbo].[TB_USUARIOS] ON
GO

INSERT INTO [dbo].[TB_USUARIOS] ([ID], [NOME], [SENHA], [NIVEL], [CPF], [CREATED], [MODIFIED], [ATIVO], [USUARIO]) VALUES (N'1', N'JOÃO MANOEL MEIRA DA SILVA', N'54fb1698ce33b92a89151b496e8c1fcb', N'1', N'398.114.718-94', NULL, NULL, NULL, NULL)
GO


-- ----------------------------
-- Auto increment value for TB_USUARIOS
-- ----------------------------
DBCC CHECKIDENT ('[dbo].[TB_USUARIOS]', RESEED, 1)
GO


-- ----------------------------
-- Primary Key structure for table TB_USUARIOS
-- ----------------------------
ALTER TABLE [dbo].[TB_USUARIOS] ADD CONSTRAINT [PK__TB_USUAR__3214EC27F57F946F] PRIMARY KEY CLUSTERED ([ID])
WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON)  
ON [PRIMARY]
GO

