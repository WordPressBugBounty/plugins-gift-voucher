<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

define('FPDF_FONTPATH',WPGIFT__PLUGIN_DIR .'/library/fpdf/font');
require('fpdf.php');
require('htmlparser.inc');

class WPGV_PDF_HTML_ROTATE extends FPDF
{
	var $angle=0;
	protected $B;
    protected $I;
    protected $U;
    protected $HREF;
    protected $fontList;
    protected $issetfont;
    protected $issetcolor;

    function __construct($orientation='P', $unit='pt', $format='A4')
    {
        //Call parent constructor
        parent::__construct($orientation,$unit,$format);
        //Initialization
        $this->B=0;
        $this->I=0;
        $this->U=0;
        $this->HREF='';
        $this->fontlist=array('arial', 'times', 'courier', 'helvetica', 'symbol');
        $this->issetfont=false;
        $this->issetcolor=false;
    }

    function WriteHTML2($html)
    {
        //HTML parser
        $html=str_replace("\n",' ',$html);
        $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
        foreach($a as $i=>$e)
        {
            if($i%2==0)
            {
                //Text
                if($this->HREF)
                    $this->PutLink($this->HREF,$e);
                else
                    $this->Write(5,$e);
            }
            else
            {
                //Tag
                if($e[0]=='/')
                    $this->CloseTag(strtoupper(substr($e,1)));
                else
                {
                    //Extract attributes
                    $a2=explode(' ',$e);
                    $tag=strtoupper(array_shift($a2));
                    $attr=array();
                    foreach($a2 as $v)
                    {
                        if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
                            $attr[strtoupper($a3[1])]=$a3[2];
                    }
                    $this->OpenTag($tag,$attr);
                }
            }
        }
    }

    function OpenTag($tag, $attr)
    {
        //Opening tag
        switch($tag){
            case 'STRONG':
                $this->SetStyle('B',true);
                break;
            case 'EM':
                $this->SetStyle('I',true);
                break;
            case 'B':
            case 'I':
            case 'U':
                $this->SetStyle($tag,true);
                break;
            case 'A':
                $this->HREF=$attr['HREF'];
                break;
            case 'IMG':
                if(isset($attr['SRC']) && (isset($attr['WIDTH']) || isset($attr['HEIGHT']))) {
                    if(!isset($attr['WIDTH']))
                        $attr['WIDTH'] = 0;
                    if(!isset($attr['HEIGHT']))
                        $attr['HEIGHT'] = 0;
                    $this->Image($attr['SRC'], $this->GetX(), $this->GetY(), wpgv_px2mm($attr['WIDTH']), wpgv_px2mm($attr['HEIGHT']));
                }
                break;
            case 'TR':
            case 'BLOCKQUOTE':
            case 'BR':
                $this->Ln(5);
                break;
            case 'P':
                $this->Ln(10);
                break;
            case 'FONT':
                if (isset($attr['COLOR']) && $attr['COLOR']!='') {
                    $coul=wpgv_hex2rgb($attr['COLOR']);
                    $this->SetTextColor($coul['R'],$coul['V'],$coul['B']);
                    $this->issetcolor=true;
                }
                if (isset($attr['FACE']) && in_array(strtolower($attr['FACE']), $this->fontlist)) {
                    $this->SetFont(strtolower($attr['FACE']));
                    $this->issetfont=true;
                }
                break;
        }
    }

    function CloseTag($tag)
    {
        //Closing tag
        if($tag=='STRONG')
            $tag='B';
        if($tag=='EM')
            $tag='I';
        if($tag=='B' || $tag=='I' || $tag=='U')
            $this->SetStyle($tag,false);
        if($tag=='A')
            $this->HREF='';
        if($tag=='FONT'){
            if ($this->issetcolor==true) {
                $this->SetTextColor(0);
            }
            if ($this->issetfont) {
                $this->SetFont('arial');
                $this->issetfont=false;
            }
        }
    }

    function SetStyle($tag, $enable)
    {
        //Modify style and select corresponding font
        $this->$tag+=($enable ? 1 : -1);
        $style='';
        foreach(array('B','I','U') as $s)
        {
            if($this->$s>0)
                $style.=$s;
        }
        $this->SetFont('',$style);
    }

    function PutLink($URL, $txt)
    {
        //Put a hyperlink
        $this->SetTextColor(0,0,255);
        $this->SetStyle('U',true);
        $this->Write(5,$txt,$URL);
        $this->SetStyle('U',false);
        $this->SetTextColor(0);
    }

    function WriteTable($data, $w)
    {
        $this->SetLineWidth(.3);
        $this->SetFillColor(255,255,255);
        $this->SetTextColor(0);
        $this->SetFont('');
        foreach($data as $row)
        {
            $nb=0;
            for($i=0;$i<count($row);$i++)
                $nb=max($nb,$this->NbLines($w[$i],trim($row[$i])));
            $h=5*$nb;
            $this->CheckPageBreak($h);
            for($i=0;$i<count($row);$i++)
            {
                $x=$this->GetX();
                $y=$this->GetY();
                $this->Rect($x,$y,$w[$i],$h);
                $this->MultiCell($w[$i],5,trim($row[$i]),0,'C');
                //Put the position to the right of the cell
                $this->SetXY($x+$w[$i],$y);                    
            }
            $this->Ln($h);

        }
    }

    function NbLines($w, $txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb)
        {
            $c=$s[$i];
            if($c=="\n")
            {
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep=$i;
            $l+=$cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i=$sep+1;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
            }
            else
                $i++;
            }
        return $nl;
    }

    function CheckPageBreak($h)
    {
        //If the height h would cause an overflow, add a new page immediately
        if($this->GetY()+$h>$this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }   

    function ReplaceHTML($html)
    {
        $html = str_replace( '<li>', "\n<br> - " , $html );
        $html = str_replace( '<LI>', "\n - " , $html );
        $html = str_replace( '</ul>', "\n\n" , $html );
        $html = str_replace( '<strong>', "<b>" , $html );
        $html = str_replace( '</strong>', "</b>" , $html );
        $html = str_replace( '&#160;', "\n" , $html );
        $html = str_replace( '&nbsp;', " " , $html );
        $html = str_replace( '&quot;', "\"" , $html ); 
        $html = str_replace( '&#39;', "'" , $html );
        return $html;
    }

    function ParseTable($Table)
    {
        $_var='';
        $htmlText = $Table;
        $parser = new HtmlParser ($htmlText);
        while ($parser->parse())
        {
            if(strtolower($parser->iNodeName)=='table')
            {
                if($parser->iNodeType == NODE_TYPE_ENDELEMENT)
                    $_var .='/::';
                else
                    $_var .='::';
            }

            if(strtolower($parser->iNodeName)=='tr')
            {
                if($parser->iNodeType == NODE_TYPE_ENDELEMENT)
                    $_var .='!-:'; //opening row
                else
                    $_var .=':-!'; //closing row
            }
            if(strtolower($parser->iNodeName)=='td' && $parser->iNodeType == NODE_TYPE_ENDELEMENT)
            {
                $_var .='#,#';
            }
            if ($parser->iNodeName=='Text' && isset($parser->iNodeValue))
            {
                $_var .= $parser->iNodeValue;
            }
        }
        $elems = explode(':-!',str_replace('/','',str_replace('::','',str_replace('!-:','',$_var)))); //opening row
        foreach($elems as $key=>$value)
        {
            if(trim($value)!='')
            {
                $elems2 = explode('#,#',$value);
                array_pop($elems2);
                $data[] = $elems2;
            }
        }
        return $data;
    }

    function WriteHTML($html)
    {
        $html = $this->ReplaceHTML($html);
        //Search for a table
        $start = strpos(strtolower($html),'<table');
        $end = strpos(strtolower($html),'</table');
        if($start!==false && $end!==false)
        {
            $this->WriteHTML2(substr($html,0,$start).'<BR>');

            $tableVar = substr($html,$start,$end-$start);
            $tableData = $this->ParseTable($tableVar);
            for($i=1;$i<=count($tableData[0]);$i++)
            {
                if($this->CurOrientation=='L')
                    $w[] = abs(120/(count($tableData[0])-1))+24;
                else
                    $w[] = abs(120/(count($tableData[0])-1))+5;
            }
            $this->WriteTable($tableData,$w);

            $this->WriteHTML2(substr($html,$end+8,strlen($html)-1).'<BR>');
        }
        else
        {
            $this->WriteHTML2($html);
        }
    }

	function Rotate($angle,$x=-1,$y=-1)
	{
		if($x==-1)
			$x=$this->x;
		if($y==-1)
			$y=$this->y;
		if($this->angle!=0)
			$this->_out('Q');
		$this->angle=$angle;
		if($angle!=0)
		{
			$angle*=M_PI/180;
			$c=cos($angle);
			$s=sin($angle);
			$cx=$x*$this->k;
			$cy=($this->h-$y)*$this->k;
			$this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
		}
	}

	function _endpage()
	{
		if($this->angle!=0)
		{
			$this->angle=0;
			$this->_out('Q');
		}
		parent::_endpage();
	}
}

