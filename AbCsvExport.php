<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CSVExport
 *
 * @author Ariel Braun relbraun@gmail.com
 * @copyright (c) 2013
 * 
 * $controller->widget('CSVExport',array(
 *              'dataPrivider'=>$dataProvider,
 *              'columns' => array(
 *                  'column1',
 *                  'column2',
 *              )
 *          );
 */
 Yii::import('zii.widgets.CBaseListView');
 Yii::import('zii.widgets.grid.CDataColumn');
 
class AbCsvExport extends CBaseListView
{
	const FILTER_POS_HEADER='header';
	const FILTER_POS_FOOTER='footer';
	const FILTER_POS_BODY='body';
	
	public $filterPosition='body';
        
        public $pagination=false;
    /**
     * show column headers in csv file
     * @var boolean $withHeaders
     */
    public $hideHeader=false;
    /**
     * the data provider of exporting
     * @var CActiveDataProvider $dataProvider
     */
    public $dataProvider;
    /**
     * get columns in csv file
     * @var array $columns
     */
    public $columns=array();
    /**
     * get column headers in csv file
     * @var array $_headers
     */
    protected $_headers=array();
    
    protected $_handle;
	
    protected $_row=array();
    
    protected $_delimiter = ",";
    
    protected $nullDisplay=" ";
    
    protected $tempName='php://temp';
    
    /**
     * string to enclose fields when delimiter is found in field
     * @var string $_enclosure 
     */
    protected $_enclosure = '"';
    
    public function init()
    {
        parent::init();
        $this->dataProvider->pagination=false;
        $this->dataProvider->getData(true);
        $this->_handle=fopen($this->tempName, 'w');
        $this->initColumns();
        
    }
    
    protected function initColumns()
	{
		if($this->columns===array())
		{
			if($this->dataProvider instanceof CActiveDataProvider)
                            //throw new Exception;
                            $this->columns=$this->dataProvider->model->attributeNames();
			else if($this->dataProvider instanceof IDataProvider)
			{
				// use the keys of the first row of data as the default columns
				$data=$this->dataProvider->getData();
				if(isset($data[0]) && is_array($data[0]))
					$this->columns=array_keys($data[0]);
			}
		}
		$id=$this->getId();
		foreach($this->columns as $i=>$column)
		{
			if(is_string($column))
				$column=$this->createDataColumn($column);
			else
			{
				if(!isset($column['class']))
					$column['class']='CDataColumn';
				$column=Yii::createComponent($column, $this);
			}
			if(!$column->visible)
			{
				unset($this->columns[$i]);
				continue;
			}
			if($column->id===null)
				$column->id=$id.'_c'.$i;
			$this->columns[$i]=$column;
		}

		foreach($this->columns as $column)
			$column->init();
	}

	/**
	 * Creates a {@link CDataColumn} based on a shortcut column specification string.
	 * @param string $text the column specification string
	 * @return CDataColumn the column instance
	 */
	protected function createDataColumn($text)
	{
		if(!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/',$text,$matches))
			throw new CException(Yii::t('zii','The column must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.'));
		$column=new CDataColumn($this);
		$column->name=$matches[1];
		if(isset($matches[3]) && $matches[3]!=='')
			$column->type=$matches[3];
		if(isset($matches[5]))
			$column->header=$matches[5];
		return $column;
	}

    
    public function renderItems()
	{
		if($this->dataProvider->getItemCount()>0)
		{
			//echo "<table class=\"{$this->itemsCssClass}\">\n";
			$this->renderTableHeader();
			
			$this->renderTableBody();
			
			//$this->renderTableFooter();
			//echo $body; // TFOOT must appear before TBODY according to the standard.
			//echo "</table>";
		}
		else
			$this->renderEmptyText();
	}
        
        public function renderTableHeader()
	{
		if(!$this->hideHeader)
		{
			//echo "<thead>\n";
			$this->_row=array();
			

			//echo "<tr>\n";
			foreach($this->columns as $column)
				$this->renderHeaderCell($column);
                            
			fputcsv($this->_handle, $this->_row, $this->_delimiter, $this->_enclosure);
		}
		
	}
	
	protected function renderHeaderCell(CDataColumn $column)
	{
		$data;
		if($column->visible)
		{
			if($this->dataProvider instanceof CActiveDataProvider)
				$data=$this->dataProvider->model->getAttributeLabel($column->name);
			else
				$data=$column->name;
			array_push($this->_row,isset($column->header)?$column->header:$data);
		}
	}
	
	public function renderFilter()
	{
		if($this->filter!==null)
		{
			//echo "<tr class=\"{$this->filterCssClass}\">\n";
			foreach($this->columns as $column)
				$this->renderFilterCell($column);
			//echo "</tr>\n";
		}
	}
	
	protected function renderFilterCell(CDataColumn $column)
	{
		
	}
        
        public function renderTableBody()
	{
		$data=$this->dataProvider->getData();
		$n=count($data);
		

		if($n>0)
		{
			for($row=0;$row<$n;++$row)
				$this->renderTableRow($row);
		}
		else
		{
			//echo '<tr><td colspan="'.count($this->columns).'" class="empty">';
			$this->renderEmptyText();
			//echo "</td></tr>\n";
		}
		//echo "</tbody>\n";
	}

	/**
	 * Renders a table body row.
	 * @param integer $row the row number (zero-based).
	 */
	public function renderTableRow($row)
	{
		
		$data=$this->dataProvider->data[$row];
		$this->_row=array();
		foreach($this->columns as $column)
			$this->renderDataCell($column,$row);
                //throw new CException($this->_row[2]);//var_dump($this->_row);
		fputcsv($this->_handle, $this->_row, $this->_delimiter, $this->_enclosure);
	}
	
	public function renderDataCell($column,$row)
	{
		$data=$this->dataProvider->data[$row];
		
		
		//echo CHtml::openTag('td',$options);
		$this->renderDataCellContent($column,$row,$data);
		//echo '</td>';
	}
	
	protected function renderDataCellContent($column,$row,$data)
	{
		if($column->value!==null)
			$value=$column->evaluateExpression($column->value,array('data'=>$data,'row'=>$row));
		elseif($column->name!==null)
			$value=CHtml::value($data,$column->name);
		array_push($this->_row,$value===null ? $this->nullDisplay : $value);
	}
        
        protected function setPagination($value=false)
        {
            $this->dataProvider=$value;
        }
        
        public function run()
        {
            //$this->dataProvider->pagination->pageSize=20;
            $this->renderItems();
            rewind($this->_handle);
            //$content=file_put_contents(Yii::app()->name.'.csv', $this->_handle, FILE_APPEND | LOCK_EX);
           // $content=stream_get_contents($this->_handle);
            $content=  fread($this->_handle,9000000);
            Yii::app()->getRequest()->sendFile(Yii::app()->name.' '.date('d-m-Y').'.csv',
                    chr(239) . chr(187) . chr(191) .$content, "text/csv", false);
            fclose($this->_handle);
        }
}

?>
