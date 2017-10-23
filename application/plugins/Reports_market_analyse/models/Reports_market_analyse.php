<?php
/* Group Name: Продажи
 * User Level: 2
 * Plugin Name: Анализ отчетов маркетов
 * Plugin URI: http://isellsoft.com
 * Version: 0.1
 * Description: Анализ отчетов маркетов
 * Author: baycik 2017
 * Author URI: http://isellsoft.com
 * Trigger before: Reports_market_analyse
 */
class Reports_market_analyse extends Catalog{
    public function __construct() {
	//$this->fdate=$this->dmy2iso( $this->request('fdate','\d\d.\d\d.\d\d\d\d') ).' 23:59:59';
        $this->group_by_filter=$this->request('group_by_filter');
	$this->group_by=$this->request('group_by','\w+');
        $this->pcomp_id=222;
        $this->acomp_id=2;
	parent::__construct();
    }
    public function install(){
	$install_file=__DIR__."/install.sql";
	$this->load->model('Maintain');
	return $this->Maintain->backupImportExecute($install_file);
    }
    public function uninstall(){
	$uninstall_file=__DIR__."/uninstall.sql";
	$this->load->model('Maintain');
	return $this->Maintain->backupImportExecute($uninstall_file);
    }
    public $views=['string'];
    public function views($path){
	header("X-isell-type:OK");
	$this->load->view($path);
    }
    private function dmy2iso( $dmy ){
	$chunks=  explode('.', $dmy);
	return "$chunks[2]-$chunks[1]-$chunks[0]";
    }
    
    public $reportImport=[
        'pcomp_id'=>'int',
        'idate'=>'string',
        'fdate'=>'string',
        'comment'=>'string',
        'label'=>'string',
        'affiliate'=>'string',
        'article'=>'string',
        'left'=>'int',
        'sold'=>'int'];
    public function reportImport($pcomp_id,$idate,$fdate,$comment,$label,$affiliate,$article,$left,$sold){
        $usd_ratio=$this->Hub->pref('usd_ratio');
        $acomp_id=$this->Hub->acomp('company_id');
        $sql_clear="DROP TEMPORARY TABLE IF EXISTS tmp_market_report";
        $sql_prepare="CREATE TEMPORARY TABLE tmp_market_report ( INDEX(product_code) ) ENGINE=MyISAM AS (
            SELECT
                $article article,
                product_code,
                ru product_name,
                analyse_type,
                analyse_group,
                $affiliate store_code,
                $sold sold,
                $left leftover
            FROM
                imported_data
                    LEFT JOIN
                prod_list pl ON analyse_section=B
            WHERE
                B<>'' 
                AND label='маркет')";
        
        $sql_price_setup="SET @_product_code:='',@_acomp_id:=$acomp_id,@_pcomp_id:=$pcomp_id,@_to_cstamp:='{$fdate}';";
        $sql_price_clear="DROP TEMPORARY TABLE IF EXISTS tmp_market_report_price";
        $sql_price_prepare="CREATE TEMPORARY TABLE tmp_market_report_price ( INDEX(product_code) ) ENGINE=MyISAM AS (
            SELECT 
		product_code,ROUND(SUM(qty*invoice_price)/SUM(qty),2) avg_price 
	    FROM
		(SELECT 
		    product_code,
		    invoice_price,
		    @_quantity:=IF(product_code<>@_product_code AND @_product_code:=product_code OR 1,total,@_quantity)-product_quantity q,
		    product_quantity+LEAST(0,@_quantity) qty
		FROM
		    (SELECT 
			product_code,
			product_quantity,
			invoice_price*(1+dl.vat_rate/100) invoice_price,
			total
		    FROM
			(SELECT product_code,SUM(sold)+SUM(leftover) total FROM tmp_market_report GROUP BY product_code) tmr
			    JOIN
			document_entries de USING(product_code)
			    JOIN
			document_list dl USING (doc_id)
		    WHERE
			cstamp < @_to_cstamp
			AND active_company_id=@_acomp_id
			AND passive_company_id=@_pcomp_id
		    ORDER BY product_code,cstamp DESC) sub
		) sub2
            WHERE qty>0
            GROUP BY product_code)";
        
        $sql_price_missing_clear="DROP TEMPORARY TABLE IF EXISTS tmp_market_report_missing";
        $sql_price_missing_fill="CREATE TEMPORARY TABLE tmp_market_report_missing ( INDEX(product_code) ) AS 
            (SELECT product_code,GET_PRICE(product_code,$pcomp_id,$usd_ratio) avg_price
                FROM tmp_market_report
                WHERE product_code NOT IN (SELECT product_code FROM tmp_market_report_price)
                GROUP BY product_code)";
        $sql_price_complete="INSERT INTO tmp_market_report_price SELECT * FROM tmp_market_report_missing";
        
        $this->query($sql_clear);
        $this->query($sql_prepare);
        $this->query($sql_price_setup);
        $this->query($sql_price_clear);
        $this->query($sql_price_prepare);
        $this->query($sql_price_missing_clear);
        $this->query($sql_price_missing_fill);
        $this->query($sql_price_complete);
        
        $this->query("START TRANSACTION");
        $this->query("INSERT INTO plugin_market_rpt_list SET idate='$idate',fdate='$fdate',comment='$comment',pcomp_id='$pcomp_id'");
        $report_id=$this->db->insert_id();
        $this->reportSave($report_id);
        $this->query("COMMIT");
        return true;
    }
    
    private function reportSave($report_id){
        $sql_prepare="INSERT INTO plugin_market_rpt_entries (
	    SELECT
                $report_id AS report_id,
                product_code,
                article,
                product_name,
                analyse_type,
                analyse_group,
                store_code,
                sold,
                leftover,
                '' group_by,
                avg_price,
                avg_price*sold sold_sum,
                avg_price*leftover leftover_sum
            FROM
                tmp_market_report
                    LEFT JOIN
                tmp_market_report_price USING(product_code)
            ORDER BY sold_sum<>0,sold_sum DESC,leftover_sum DESC
	    )";
        $this->query($sql_prepare);        
    }
    
    public function viewGet(){
        $this->reportFill();
        $sql_fetch="
            SELECT
		*
            FROM
                plugin_rpt_market_result";
        $sql_summary_type_fetch="
            SELECT
		group_by,
                SUM(sold) sold,
                SUM(avg_price*sold) sold_sum,
                SUM(leftover) leftover,
                SUM(avg_price*leftover) leftover_sum
            FROM
                plugin_rpt_market_result
            GROUP BY $this->group_by 
            ORDER BY sold_sum DESC";

	$rows=$this->get_list($sql_fetch);
	$sum_rows=$this->get_list($sql_summary_type_fetch);
	return [
	    'rows'=>count($rows)?$rows:[[]],
	    'sum_rows'=>count($sum_rows)?$sum_rows:[[]],
	    'sum'=>$this->calc_sum($sum_rows)
	];
    }
    private function calc_sum($sum_rows){
	$sum=[
	    'sum_sold'=>0,
	    'sum_leftover'=>0,
	    'sum_sold_sum'=>0,
	    'sum_leftover_sum'=>0
	];
	foreach($sum_rows as $row){
	    $sum['sum_sold']+=$row->sold;
	    $sum['sum_leftover']+=$row->leftover;
	    $sum['sum_sold_sum']+=$row->sold_sum;
	    $sum['sum_leftover_sum']+=$row->leftover_sum;
	}
	return $sum;
    }
    
    
    public $listFetch=[];
    public function listFetch(){
	return [];
    }
}