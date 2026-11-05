
IF EXISTS (SELECT * FROM sys.all_objects WHERE object_id = OBJECT_ID(N'[dbo].[TB_UNIDADES]') AND type IN ('U'))
	DROP TABLE [dbo].[TB_UNIDADES]
GO

CREATE TABLE [dbo].[TB_UNIDADES] (
  [id] int  IDENTITY(1,1) NOT NULL,
  [unidade] nvarchar(255) COLLATE Latin1_General_CI_AS  NOT NULL,
  [data_cadastro] datetime DEFAULT getdate() NULL
)
GO

ALTER TABLE [dbo].[TB_UNIDADES] SET (LOCK_ESCALATION = TABLE)
GO


-- ----------------------------
-- Records of TB_UNIDADES
-- ----------------------------
SET IDENTITY_INSERT [dbo].[TB_UNIDADES] ON
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'1', N'EMEF "SECRETARIA DE EDUCAÇÃO"', N'2025-06-09 09:48:57.170')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'2', N'NISC "RECRIANÇA"', N'2025-06-09 09:49:42.577')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'3', N'EMEIF “PROFA. MARIA INÊZ DOS SANTOS”', N'2025-06-09 09:50:03.663')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'4', N'EMEIF “PROF. AFFONSO BASILE” - PEBI', N'2025-06-09 09:50:29.007')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'5', N'EMEIF “PROF. AFFONSO BASILE” - PEBII', N'2025-06-09 09:50:34.330')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'6', N'EMEIF “MARIA SALETE BASILE SALES”', N'2025-06-09 09:50:57.693')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'7', N'EMEIF "PROFA.DIVA MORAES CAMARGO PUCCI"', N'2025-06-09 09:51:05.620')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'8', N'EMEIF "FAZENDA POLENGHI"', N'2025-06-09 09:51:15.853')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'9', N'EMEIF "BAIRRO SERRA DA BOA VISTA"', N'2025-06-09 09:51:24.953')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'10', N'EMEIF "BAIRRO BATALHEIRA"', N'2025-06-09 09:51:32.363')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'11', N'EMEF "PROFA.MARIA ISABEL LOPES DE OLIVEIRA"', N'2025-06-09 09:51:48.000')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'12', N'EM “PROFESSORA HERMÍNIA ARAÚJO”', N'2025-06-09 09:52:04.967')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'13', N'CMEI “VÓ CARMELA”', N'2025-06-09 09:52:21.437')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'14', N'CMEI "VÓ IA"', N'2025-06-09 09:52:27.580')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'15', N'CMEI "MENINO JESUS"', N'2025-06-09 09:52:35.510')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'16', N'CMEI "CHICA GORDA"', N'2025-06-09 09:52:41.553')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'17', N'CEMEIF “VÓ VIRGÍNIA”', N'2025-06-09 09:52:49.020')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'18', N'CEMEIF “VÓ JOANINHA”', N'2025-06-09 09:52:55.207')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'19', N'CEMEIF "NHÁ NICA"', N'2025-06-09 09:53:02.870')
GO

INSERT INTO [dbo].[TB_UNIDADES] ([id], [unidade], [data_cadastro]) VALUES (N'20', N'EMEF "DR. FORTUNATO DE CAMARGO"', N'2025-06-10 10:06:44.023')
GO

SET IDENTITY_INSERT [dbo].[TB_UNIDADES] OFF
GO


-- ----------------------------
-- Auto increment value for TB_UNIDADES
-- ----------------------------
DBCC CHECKIDENT ('[dbo].[TB_UNIDADES]', RESEED, 20)
GO


-- ----------------------------
-- Primary Key structure for table TB_UNIDADES
-- ----------------------------
ALTER TABLE [dbo].[TB_UNIDADES] ADD CONSTRAINT [PK__TB_UNIDA__3213E83F4329BB83] PRIMARY KEY CLUSTERED ([id])
WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON)  
ON [PRIMARY]
GO

