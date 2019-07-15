CREATE SCHEMA account
GO
CREATE SCHEMA master
GO
CREATE SCHEMA stock
GO
CREATE SCHEMA document
GO

CREATE TABLE [document].[request_tools](
	[req_tools_code] [varchar](20) NOT NULL,
	[menu_page] [varchar](20) NOT NULL,
	[req_tools_date] [date] NOT NULL DEFAULT(GETDATE()),
	[req_nik] [varchar](10) NULL,
	[name_of_request] [varchar](30) NOT NULL,
	[create_by] [varchar](10) NOT NULL,
	[create_date] [datetime] NOT NULL DEFAULT(GETDATE()),
	[status] [varchar](10) NOT NULL DEFAULT(('ST02')),
	[finish_by] [varchar](10) NULL,
	[finish_date] [datetime] NULL
) ON [PRIMARY]
GO

CREATE TABLE [document].[request_tools_detail](
	[req_tools_code] [varchar](20) NOT NULL,
	[stock_code] [varchar](10) NOT NULL,
	[req_tools_qty] [decimal](20,2) NOT NULL,
	[req_tools_notes] [varchar](255) NULL,
	[fullfillment] [bit] NOT NULL DEFAULT((1)),
	[finish_by] [varchar](10) NULL,
	[finish_date] [datetime] NULL
) ON [PRIMARY]
GO

CREATE TABLE [document].[purchase_order](
	[po_code] [varchar](20) NOT NULL,
	[menu_page] [varchar](20) NOT NULL,
	[menu_page_destination] [varchar](20) NOT NULL,
	[po_date] [date] NOT NULL DEFAULT(GETDATE()),
	[nik] [varchar](10) NOT NULL,
	[create_by] [varchar](10) NOT NULL,
	[create_date] [datetime] NOT NULL DEFAULT(GETDATE()),
	[status] [varchar](10) NOT NULL DEFAULT(('ST06')),
	[finish_by] [varchar](10) NOT NULL,
	[finish_date] [datetime] NOT NULL DEFAULT(GETDATE())
) ON [PRIMARY]
GO

CREATE TABLE [document].[po_detail](
	[po_code] [varchar](20) NOT NULL,
	[stock_code] [varchar](10) NOT NULL,
	[po_qty] [decimal](20,2) NOT NULL,
	[po_notes] [varchar](255) NULL
) ON [PRIMARY]
GO

CREATE TABLE [master].[master_page](
	[page_code] [varchar](10) NOT NULL,
	[page_name] [varchar](20) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_measure](
	[measure_code] [varchar](10) NOT NULL,
	[measure_type] [varchar](50) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_category](
	[category_code] [varchar](5) NOT NULL,
	[category_name] [varchar](50) NOT NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_stock](
	[stock_code] [varchar](10) NOT NULL,
	[stock_name] [varchar](50) NULL,
	[stock_size] [varchar](20) NULL,
	[stock_brand] [varchar](20) NULL,
	[stock_type] [varchar](20) NULL,
	[stock_color] [varchar](20) NULL,
	[stock_description] [varchar](50) NULL,
	[measure_code] [varchar](10) NOT NULL,
	[stock_min_qty] [decimal](20,2) NULL,
	[stock_max_qty] [decimal](20,2) NULL,
	[stock_daily_use] [char](1) NOT NULL DEFAULT((0))
) ON [PRIMARY]
GO
CREATE TABLE [stock].[measure_convert](
	[stock_code] [varchar](10) NOT NULL,
	[measure_code] [varchar](10) NOT NULL,
	[measure_qty] [varchar](10) NOT NULL,
) ON [PRIMARY]
GO
CREATE TABLE [stock].[stock](
	[main_stock_code] [varchar](10) NOT NULL,
	[stock_code] [varchar](10) NOT NULL,
	[menu_page] [varchar](10) NOT NULL,
	[nik] [varchar](10) NOT NULL,
	[main_stock_date] [datetime] NOT NULL DEFAULT(GETDATE()),
) ON [PRIMARY]
GO
CREATE TABLE [stock].[qty](
	[main_stock_code] [varchar](10) NOT NULL,
	[supplier_code] [varchar](10) NOT NULL,
	[stock_price] [decimal](20,2) NULL DEFAULT((0)),
	[qty] [decimal](20,2) NOT NULL,
	[nik] [varchar](20) NOT NULL,
	[stock_date] [datetime] NOT NULL DEFAULT(GETDATE()),
	[po_code] [varchar](10) NULL,
	[stock_notes] [varchar](255) NULL
) ON [PRIMARY]
GO
CREATE TABLE [stock].[qty_out](
	[main_stock_code] [varchar](10) NOT NULL,
	[supplier_code] [varchar](10) NOT NULL,
	[stock_price] [decimal](20,2) NULL DEFAULT((0)),
	[qty] [decimal](20,2) NOT NULL,
	[nik] [varchar](20) NOT NULL,
	[stock_date] [datetime] NOT NULL DEFAULT(GETDATE()),
	[stock_out_date] [datetime] NOT NULL DEFAULT(GETDATE()),
	[po_code] [varchar](10) NULL,
	[stock_notes] [varchar](255) NULL
) ON [PRIMARY]
GO
CREATE TABLE [stock].[cabinet](
	[stock_cabinet_code] [varchar](10) NOT NULL,
	[menu_page] [varchar](20) NOT NULL,
	[cabinet_code] [varchar](10) NOT NULL,
	[main_stock_code] [varchar](10) NOT NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_supplier](
	[supplier_code] [varchar](10) NOT NULL,
	[supplier_name] [varchar](20) NULL,
	[supplier_address] [varchar](20) NULL,
	[city_code] [varchar](10) NULL,
	[supplier_phone] [varchar](20) NULL,
	[supplier_category] [varchar](20) NULL,
	[status_code] [varchar](4) NULL DEFAULT('ST01'),
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_city](
	[city_code] [varchar](10) NOT NULL,
	[city_name] [varchar](20) NULL,
	[status_code] [varchar](4) NULL,
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_cabinet](
	[cabinet_code] [varchar](10) NOT NULL,
	[cabinet_name] [varchar](4) NULL,
	[cabinet_description] [varchar](255) NULL,
	[menu_page] [varchar](20) NULL,
	[parent_cabinet_code] [varchar](10) NULL,
	[is_child] [integer] NULL DEFAULT((1)),
	[status_code] [varchar](4) NULL DEFAULT('ST01'),
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_marital](
	[marital_code] [varchar](10) NOT NULL,
	[marital_label] [varchar](100) NULL,
	[marital_description] [varchar](255) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_company](
	[company_code] [varchar](10) NOT NULL,
	[company_name] [varchar](100) NULL,
	[company_description] [varchar](255) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_department](
	[department_code] [varchar](10) NOT NULL,
	[company_code] [varchar](10) NOT NULL,
	[department_name] [varchar](100) NULL,
	[department_description] [varchar](255) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_division](
	[division_code] [varchar](10) NOT NULL,
	[department_code] [varchar](10) NOT NULL,
	[company_code] [varchar](10) NOT NULL,
	[division_name] [varchar](100) NULL,
	[division_description] [varchar](255) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_identity](
	[identity_code] [varchar](10) NOT NULL,
	[identity_label] [varchar](100) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_status](
	[status_code] [varchar](4) NOT NULL,
	[status_label] [varchar](100) NULL
) ON [PRIMARY]
GO
CREATE TABLE [master].[master_menu](
	[id_menu] [integer] NOT NULL,
	[menu_page] [varchar](20) NOT NULL,
	[menu_name] [varchar](20) NOT NULL,
	[menu_url] [varchar](20) NOT NULL,
	[menu_icon] [varchar](20) NULL,
	[id_parent] [integer] NULL
) ON [PRIMARY]
GO
CREATE TABLE [account].[user_menu](
	[company_code] [varchar](10) NOT NULL,
	[department_code] [varchar](10) NOT NULL,
	[division_code] [varchar](10) NOT NULL,
	[id_menu] [integer] NOT NULL,
) ON [PRIMARY]
GO
CREATE TABLE [account].[user_biodata](
	[nik] [varchar](20) NOT NULL,
	[first_name] [varchar](50) NOT NULL,
	[last_name] [varchar](50) NULL,
	[birthday] [date] NULL,
	[email] [varchar](100) NULL,
	[handphone] [varchar](20) NULL,
	[phone] [varchar](20) NULL,
	[marital_code] [varchar](10) NULL
) ON [PRIMARY]
GO
CREATE TABLE [account].[user_identity](
	[nik] [varchar](20) NOT NULL,
	[identity_code] [varchar](10) NULL,
	[identity_number] [varchar](50) NULL,
	[address] [varchar](150) NULL,
	[post_code] [varchar](10) NULL,
) ON [PRIMARY]
GO
CREATE TABLE [account].[user](
	[nik] [varchar](20) NOT NULL,
	[pwd_hash] [varchar](255) NULL,
	[company_code] [varchar](10) NULL,
	[department_code] [varchar](10) NULL,
	[division_code] [varchar](10) NULL,
	[status_code] [varchar](4) NULL,
	[last_login] [datetime] NULL,
) ON [PRIMARY]
GO

-- store procedure
CREATE PROCEDURE stock.stock_out
	@stcode VARCHAR(20),
	@qty DECIMAL(20,2),
	@nik VARCHAR(10),
	@page VARCHAR(10),
	@notes VARCHAR(255)
AS
	SET NOCOUNT ON 
	DECLARE @cmin INT, @cmax INT
	
	DROP TABLE IF EXISTS #temp
	
	IF EXISTS (SELECT * FROM dbo.sysobjects where id = object_id(N'#temp') and OBJECTPROPERTY(id, N'IsTable') = 1)
		BEGIN
			TRUNCATE TABLE #temp
		END
	
	IF NOT EXISTS (SELECT * FROM dbo.sysobjects where id = object_id(N'#temp') and OBJECTPROPERTY(id, N'IsTable') = 1)
		BEGIN
			CREATE TABLE #temp (
				id INT,
				main_stock_code VARCHAR(20),
				supplier_code VARCHAR(20),
				stock_price DECIMAL(20,2),
				stock_date DATETIME,
				qty DECIMAL(20,2)
			)
		END
	
	INSERT INTO #temp SELECT ROW_NUMBER() OVER(ORDER BY stock_date ASC) AS id, stock.qty.main_stock_code, supplier_code, stock_price, stock_date, sum(qty) AS qty
	FROM stock.qty
	JOIN stock.stock ON stock.stock.main_stock_code = stock.qty.main_stock_code
	WHERE qty > 0 AND stock_code = @stcode
	GROUP BY stock.qty.main_stock_code, supplier_code, stock_price, stock_date
	ORDER BY stock_date ASC
	
	SELECT @cmin=min(id), @cmax=max(id) FROM #temp
	WHILE @cmin <= @cmax
	BEGIN
		-- getting data qty
		DECLARE @main_stock_code VARCHAR(20), 
			@supplier_code VARCHAR(20), 
			@stock_price DECIMAL(20,2), 
			@stock_date DATETIME, 
			@stock_qty DECIMAL(20,2)
	
		SELECT @main_stock_code = main_stock_code, @supplier_code = supplier_code, @stock_price = stock_price, @stock_date = stock_date, @stock_qty = qty FROM #temp WHERE id = @cmin
	
		-- processing movement stock
		IF @qty >= @stock_qty
			BEGIN
				-- insert all of stock into new stock out table
				INSERT INTO stock.qty_out(main_stock_code, supplier_code, stock_price, stock_date, qty, nik, stock_notes) VALUES(@main_stock_code, @supplier_code, @stock_price, @stock_date, @stock_qty, @nik, @notes)
				-- update to zero stock already
				UPDATE stock.qty SET qty = 0 WHERE main_stock_code = @main_stock_code AND supplier_code = @supplier_code AND stock_price = @stock_price AND stock_date = @stock_date
				-- update stock needed leftovers
				SET @qty = @qty - @stock_qty
			END
		ELSE IF @qty < @stock_qty
			BEGIN
				-- insert last stock needed to new stock out table
				INSERT INTO stock.qty_out(main_stock_code, supplier_code, stock_price, stock_date, qty, nik, stock_notes) VALUES(@main_stock_code, @supplier_code, @stock_price, @stock_date, @qty, @nik, @notes)
				-- update stock already
				UPDATE stock.qty SET qty = @stock_qty - @qty WHERE main_stock_code = @main_stock_code AND supplier_code = @supplier_code AND stock_price = @stock_price AND stock_date = @stock_date
				-- update stock needed leftovers
				SET @qty = 0
			END
		
		IF @qty = 0
			BREAK
		SET @cmin = @cmin+1
	END
	SELECT 1
RETURN

-- insert default data
INSERT INTO [master].[master_status](status_code,status_label) VALUES
('ST00', 'Tidak Aktif'),
('ST01', 'Aktif'),
('ST02', 'Proses'),
('ST03', 'Tidak cukup'),
('ST04', 'Pembelian'),
('ST05', 'Selesai'),
('ST06', 'Menunggu')
GO
INSERT INTO [master].[master_page] VALUES('wh', 'Warehaouse'),('mk','Marketing')
GO
INSERT INTO [master].[master_measure] VALUES('MEA001', 'Kg'),('MEA002', 'Liter'),('MEA003', 'Meter')
GO
INSERT INTO [master].[master_city](city_code,city_name,status_code) VALUES('BDG','Bandung','ST01'),
('JKT','Jakarta','ST01'),
('BGR','Bogor','ST01')
GO
INSERT INTO [master].[master_supplier](supplier_code, supplier_name, supplier_address, supplier_phone, supplier_category, city_code, status_code) VALUES
('SPLR1', 'Supplier 1', 'Bandung', '02289874779', 'CAT1', 'BDG','ST01'),
('SPLR2', 'Supplier 2', 'Jakarta', '02178236423', 'CAT3', 'JKT','ST01')
GO
INSERT INTO [master].[master_menu](id_menu,menu_page, menu_name, menu_url,menu_icon,id_parent) VALUES
(1, 'wh', 'Beranda', '/', 'fa fa-home', NULL),
(2, 'wh', 'Request', '/', 'fa fa-upload', NULL),
(3, 'wh', 'Purchase Order', '/req/po', 'fa fa-file', 2),
(4, 'wh', 'Tentang', '/about', 'fa fa-link', NULL),
(5, 'wh', 'Master', '/', 'fa fa-box', NULL),
(6, 'wh', 'Stock', '/mst/stock', 'fa fa-boxes', 5),
(7, 'mk', 'Beranda', '/', 'fa fa-home', NULL),
(8, 'mk', 'Master', '/', 'fa fa-box', NULL),
(9, 'mk', 'Supplier', '/mst/supplier', 'fa fa-users', 8),
(10, 'wh', 'Stock', '/', 'fa fa-boxes', NULL),
(11, 'wh', 'Rak', '/stk/cabinet', 'fa fa-users', 10),
(12, 'wh', 'Stok', '/stk/stock', 'fa fa-boxes', 10),
(13, 'wh', 'Riwayat', '/stk/history', 'fa fa-file-alt', 10),
(14, 'wh', 'Barang', '/req/tools', 'fa fa-hammer', 2),
(15, 'wh', 'Satuan', '/mst/measure', 'fa fa-balance-scale', 5),
(16, 'wh', 'Kategory', '/mst/category', 'fa fa-box-open', 5)
GO
INSERT INTO [master].[master_company](company_code,company_name) VALUES('CP01','Sarana Makin Mulia, PT.')
GO
INSERT INTO [master].[master_department](department_code,department_name, company_code) VALUES('SMDP01', 'Departement 1','CP01')
INSERT INTO [master].[master_department](department_code,department_name, company_code) VALUES('SMDP02', 'Departement 2','CP01')
GO
INSERT INTO [master].[master_division](division_code,division_name,department_code,company_code) VALUES('SMDV01', 'Division 01', 'SMDP01', 'CP01')
INSERT INTO [master].[master_division](division_code,division_name,department_code,company_code) VALUES('SMDV02', 'Division 02', 'SMDP01', 'CP01')
INSERT INTO [master].[master_division](division_code,division_name,department_code,company_code) VALUES('SMDV03', 'Division 01', 'SMDP02', 'CP01')
INSERT INTO [master].[master_division](division_code,division_name,department_code,company_code) VALUES('SMDV04', 'Division 02', 'SMDP02', 'CP01')
INSERT INTO [master].[master_division](division_code,division_name,department_code,company_code) VALUES('SMDV05', 'Division 03', 'SMDP02', 'CP01')
GO
INSERT INTO [master].[master_status](status_code,status_label) VALUES('ST00', 'Not Active'), ('ST01', 'Active')
GO
INSERT INTO [account].[user_menu](company_code, department_code, division_code, id_menu) VALUES
('CP01', 'SMDP01', 'SMDV01', 1),
('CP01', 'SMDP01', 'SMDV01', 2),
('CP01', 'SMDP01', 'SMDV01', 3),
('CP01', 'SMDP01', 'SMDV01', 4),
('CP01', 'SMDP01', 'SMDV01', 5),
('CP01', 'SMDP01', 'SMDV01', 6),
('CP01', 'SMDP01', 'SMDV02', 7),
('CP01', 'SMDP01', 'SMDV02', 8),
('CP01', 'SMDP01', 'SMDV02', 9),
('CP01', 'SMDP01', 'SMDV01', 10),
('CP01', 'SMDP01', 'SMDV01', 11),
('CP01', 'SMDP01', 'SMDV01', 12),
('CP01', 'SMDP01', 'SMDV01', 13),
('CP01', 'SMDP01', 'SMDV01', 14),
('CP01', 'SMDP01', 'SMDV01', 15),
('CP01', 'SMDP01', 'SMDV01', 16)
GO
INSERT INTO [account].[user](nik,pwd_hash,company_code,department_code,division_code,status_code) VALUES('SMM01001', '$2y$12$rbfkWNlw4gj7.OxIm80UsOte/uvI9Cb3Ndn6/TlGHty5LtT3N49vW', 'CP01', 'SMDP01', 'SMDV01', 'ST01'),('SMM01002', '$2y$12$rbfkWNlw4gj7.OxIm80UsOte/uvI9Cb3Ndn6/TlGHty5LtT3N49vW', 'CP01', 'SMDP01', 'SMDV02', 'ST01')
GO