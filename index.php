 <?
/*
(C) Copyright by ALXR. 
Original posted at https://github.com/loginsinex/webnes/
*/
?>

<html>
<head>
    <title>NES compiler</title>
</head>
<body>

<form action="/nes/" method="POST">
<table width="100%">
<tr>
    <td>
	ORG: <input type="text" name="org" value="<? echo ( (strlen(trim($_POST['org']))) > 0 ? htmlspecialchars(trim($_POST['org'])) : '$8000' ); ?>"/>
    </td>
</tr>
<tr>
    <td>
    <textarea name="code" cols="150" rows="30"><? echo $_POST['code']; ?></textarea>
    </td>
</tr>
<tr>
    <td>
    <input type="submit" value="Apply"/>
    </td>
</tr>
</table>
</form>
<hr/>
<?

    $strs = explode("\n", $_POST['code']);
    
    $opcodes = array();
    $cline = 1;
    $result = true;
    foreach($strs as $rawstr)
    {
	$c = explode(";", $rawstr);
	
	$line = trim($c[0]);
	if ( strlen($line) > 0 )
	{
	    if ( !parse($line, $opcodes, $cline) )
	    {
		echo "<span style=\"color: red;\">Syntax error at line {$cline}: " . htmlspecialchars($rawstr) . "</span><br/>\n";
		$result = false;
		break;
	    }
	}
	
	$cline++;
    }
    
    if ( $result )
    {
	$errstr = '';
	$bytes = '';
	$org = (int) hexdec(substr(trim($_POST['org']), 1));
	
	if ( !assemble($opcodes, $org, $errstr) )
	{
	    echo "<span style=\"color: red;\">{$errstr}</span><br/>\n";
	}
	else
	{
	    if ( count($opcodes) > 0 ) file_put_contents("./" . $_SERVER['REMOTE_ADDR'] . "-" . time() . ".txt", $_POST['code']);
?>
<table width="60%" style="font-family: Courier; font-size: 14px;">
<?
	    $bytes = ':' . sprintf("\$%04X", $org) . ":\n";
	    foreach($opcodes as $line)
	    {
		echo "<tr>";
		
		// address
		echo "<td align='right' width='150'>" . ( isset($line['org']) ? "" : sprintf(":\$%04X:", $line['address']) )."<td>\n";
		
		// labels
		echo "<td align='right'>" . ( $line['size'] ? "" : $line['text'] ) . "</td>\n";
		
		// instructions
		echo "<td align='left'>" . ( $line['size'] ? $line['text'] : "" ) . "</td>\n";
		
		// byte code
		
		$bt = '';
		$bt2 = '';
		if ( $line['size'] > 0 )
		{
		    foreach($line['code'] as $byte)
		    {
			$bt .= sprintf("%02X", $byte) . ' ';
			$bt2 .= sprintf("0x%02X,", $byte) . ' ';
		    }
		    
		    $bytes .= $bt2;
		}
		else if ( isset($line['org']) )
		    $bytes .= "\n\n:" . sprintf("\$%04X", $line['org']) . ":\n";
		    
		echo "<td align='left'>" . ( $line['size'] ? '; ' . trim($bt) : "" ) . "</td>\n";
		
		echo "</tr>\n";
	    }
?>
</table>
<hr/>
<table width="50%">
<tr><td><textarea name="bytes" rows="10" cols="40" style="font-family: Courier; font-size: 14px;"><? echo $bytes; ?></textarea>
</td></tr>
</table>
<hr/>
<?
	}
	
	
    }


function parse($line, &$opcodes, $cline)
{
    $op = array();
    if ( preg_match('/^org\s*(\$[0-9a-f]{4})$/i', $line, $op) )
    {
	return ORG($op[1], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*(#[0-9a-f]{2})$/i', $line, $op) )
    {
	return IMM(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*(\$[0-9a-f]{2})$/i', $line, $op) )
    {
	return ZP(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*(\$[0-9a-f]{2})\s*,\s*x$/i', $line, $op) )
    {
	return ZPX(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*(\$[0-9a-f]{4})$/i', $line, $op) )
    {
	return ABSI(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*(\$[0-9a-f]{4})\s*,\s*x$/i', $line, $op) )
    {
	return ABSX(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*(\$[0-9a-f]{4})\s*,\s*y$/i', $line, $op) )
    {
	return ABSY(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*\(\s*(\$[0-9a-f]{2})\s*\)\s*,\s*x$/i', $line, $op) )
    {
	return INDX(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*\(\s*(\$[0-9a-f]{2})\s*\)\s*,\s*y$/i', $line, $op) )
    {
	return INDY(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})$/i', $line, $op) )
    {
	return IMPL(strtoupper($op[1]), $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z]{3})\s*([a-z0-9]{1,})$/i', $line, $op) )
    {
	return REL(strtoupper($op[1]), $op[2], $opcodes, $cline);
    }
    else if ( preg_match('/^([a-z0-9]{1,}):$/i', $line, $op) )
    {
	return LABEL($op[1], $opcodes, $cline);
    }
    else
    {
	return false;
    }
	
    return true;
}

function IMM($op, $oper, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x69, 'AND' => 0x29, 'CMP' => 0xC9, 'CPX' => 0xE0, 'CPY' => 0xC0, 
		'EOR' => 0x49, 'LDA' => 0xA9, 'LDX' => 0xA2, 'LDY' => 0xA0, 'ORA' => 0x09, 
		'SBC' => 0xE9 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $asm = adechex($code) . " " . adechex(oper($oper));
    
    $c = count($opcodes);
    $opcodes[$c]['size'] = 2;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = oper($oper);
    $opcodes[$c]['text'] = "{$op} {$oper}";
    $opcodes[$c]['line'] = $cline;
    return true;
}

function ZP($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x65, 'AND' => 0x35, 'ASL' => 0x06, 'BIT' => 0x24, 'CMP' => 0xC5, 
		'CPX' => 0xE4, 'CPY' => 0xC4, 'DEC' => 0xC6, 'EOR' => 0x45, 'INC' => 0xE6, 
		'LDA' => 0xA5, 'LDX' => 0xA6, 'LDY' => 0xA0, 'LSR' => 0x46, 'ORA' => 0x05, 
		'ROL' => 0x26, 'ROR' => 0x66, 'SBC' => 0xE5, 'STA' => 0x85, 'STX' => 0x86, 
		'STY' => 0x84 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = oper($addr);
    
    $asm = adechex($code) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 2;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} {$addr}";
    $opcodes[$c]['line'] = $cline;
    
    return true;
}

function ZPX($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x75, 'AND' => 0x35, 'ASL' => 0x16, 'CMP' => 0xD5, 'DEC' => 0xD6, 
		'EOR' => 0x55, 'INC' => 0xF6, 'LDA' => 0xB5, 'LSR' => 0x56, 'ORA' => 0x15, 
		'ROL' => 0x36, 'SBC' => 0xF5, 'STA' => 0x95, 'STY' => 0x94 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = oper($addr);

    $asm = adechex($code) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 2;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} {$addr},X";
    $opcodes[$c]['line'] = $cline;
    return true;
}

function ABSI($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x6D, 'AND' => 0x2D, 'ASL' => 0x0E, 'BIT' => 0x2C, 'CMP' => 0xCD, 
		'CPX' => 0xEC, 'CPY' => 0xCC, 'DEC' => 0xCE, 'EOR' => 0x4D, 'INC' => 0xEE, 
		'JMP' => 0x4C, 'JSR' => 0x20, 'LDA' => 0xAD, 'LDX' => 0xAE, 'LDY' => 0xAC, 
		'LSR' => 0x4E, 'ORA' => 0x0D, 'ROL' => 0x2E, 'ROR' => 0x6E, 'SBC' => 0xED, 
		'STA' => 0x8D, 'STX' => 0x8E, 'STY' => 0x8C, 'JMP' => 0x4C );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = 0; $lo = 0;
    if ( !addr($addr, $hi, $lo) ) return false;
    
    $asm = adechex($code) . " " . adechex($lo) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 3;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $lo;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} {$addr}";
    $opcodes[$c]['line'] = $cline;

    return true;
}

function ABSX($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x7D, 'AND' => 0x3D, 'ASL' => 0x1E, 'CMP' => 0xDD, 'DEC' => 0xDE, 
		'EOR' => 0x5D, 'INC' => 0xFE, 'LDA' => 0xBD, 'LSR' => 0x5E, 'ORA' => 0x1D, 
		'ROL' => 0x3E, 'ROR' => 0x7E, 'SBC' => 0xFD, 'STA' => 0x9D );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = 0; $lo = 0;
    if ( !addr($addr, $hi, $lo) ) return false;

    $asm = adechex($code) . " " . adechex($lo) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 3;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $lo;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} {$addr}, X";
    $opcodes[$c]['line'] = $cline;

    return true;
}

function ABSY($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x79, 'AND' => 0x39, 'CMP' => 0xD9, 'EOR' => 0x59, 'LDA' => 0xB9, 
		'LDX' => 0xBE, 'LDY' => 0xBC, 'ORA' => 0x19, 'SBC' => 0xF9, 'STA' => 0x99 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = 0; $lo = 0;
    if ( !addr($addr, $hi, $lo) ) return false;

    $asm = adechex($code) . " " . adechex($lo) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 3;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $lo;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} {$addr}, Y";
    $opcodes[$c]['line'] = $cline;
    return true;
}

function INDX($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x61, 'AND' => 0x21, 'CMP' => 0xC1, 'EOR' => 0x41, 'LDA' => 0xA1, 
		'ORA' => 0x01, 'SBC' => 0xE1, 'STA' => 0x81 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = oper($addr);

    $asm = adechex($code) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 2;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} ({$addr}), X";
    $opcodes[$c]['line'] = $cline;
    
    return true;
}


function INDY($op, $addr, &$opcodes, $cline)
{
    $A = array( 'ADC' => 0x71, 'AND' => 0x31, 'CMP' => 0xD1, 'EOR' => 0x51, 'LDA' => 0xB1, 
		'ORA' => 0x11, 'SBC' => 0xF1, 'STA' => 0x91 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $hi = oper($addr);

    $asm = adechex($code) . " " . adechex($hi);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 2;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['code'][] = $hi;
    $opcodes[$c]['text'] = "{$op} ({$addr}), Y";
    $opcodes[$c]['line'] = $cline;

    return true;
}

function IMPL($op, &$opcodes, $cline)
{
    $A = array( 'BRK' => 0x00, 'CLC' => 0x18, 'CLD' => 0xD8, 'CLI' => 0x58, 'CLV' => 0xB8, 
		'DEX' => 0xCA, 'DEY' => 0x88, 'INX' => 0xE8, 'INY' => 0xC8, 'NOP' => 0xEA, 
		'PHA' => 0x48, 'PHP' => 0x08, 'PLA' => 0x68, 'PLP' => 0x28, 'RTI' => 0x40, 
		'RTS' => 0x60, 'SEC' => 0x38, 'SED' => 0xF8, 'SEI' => 0x78, 'TAX' => 0xAA, 
		'TAY' => 0xA8, 'TSX' => 0xBA, 'TXA' => 0x8A, 'TXS' => 0x9A, 'TYA' => 0x98, 
		'ASL' => 0x0A, 'LSR' => 0x4A );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $asm = adechex($code);

    $c = count($opcodes);
    $opcodes[$c]['size'] = 1;
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['text'] = "{$op}";
    $opcodes[$c]['line'] = $cline;

    return true;
}

function REL($op, $label, &$opcodes, $cline)
{
    $A = array( 'BCC' => 0x90, 'BCS' => 0xB0, 'BEQ' => 0xF0, 'BMI' => 0x30, 'BNE' => 0xD0, 
		'BPL' => 0x10, 'BVC' => 0x50, 'BVS' => 0x70, 'JMP' => 0x4C, 'JSR' => 0x20 );

    $code = 0;
    if ( !op($A, $op, $code) )  return false;
    
    $asm = adechex($code) . " :{$label}:";

    $c = count($opcodes);
    $opcodes[$c]['size'] = ( ( $op == 'JMP' || $op == 'JSR' ) ? 3 : 2 );
    $opcodes[$c]['code'][] = $code;
    $opcodes[$c]['text'] = "{$op} :{$label}";
    $opcodes[$c]['line'] = $cline;
    $opcodes[$c]['label'] = $label;
    $opcodes[$c]['long_label'] = ( ( $op == 'JMP' || $op == 'JSR' ) ? true : false );
    return true;
}

function LABEL($label, &$opcodes, $cline)
{
    $c = count($opcodes);
    $opcodes[$c]['size'] = 0;
    $opcodes[$c]['name'] = $label;
    $opcodes[$c]['text'] = "{$label}:";
    $opcodes[$c]['line'] = $cline;

    return true;
}

function ORG($addr, &$opcodes, $cline)
{
    $addr = (int) hexdec( substr($addr,1) );
    //o $addr;
    
    $c = count($opcodes);
    $opcodes[$c]['size'] = 0;
    $opcodes[$c]['org'] = $addr;
    $opcodes[$c]['text'] = ".ORG " . sprintf("\$%04X", $addr);
    $opcodes[$c]['line'] = $cline;

    return true;
}

function addr($x, &$hi, &$lo)
{
    $y = (int) hexdec(substr($x, 1));
    $hi = ( $y >> 8 ) & 0xFF;
    $lo = ( $y ) & 0xFF;
    return $y;
}

function oper($x)
{
    return (int) hexdec(substr($x, 1));
}

function op($A, $op, &$code)
{
    foreach($A as $k => $v)
    {
	if ( $k == $op ) 
	{
	    $code = $v;
	    return true;
	}
    }
    return false;
}

function adechex($x)
{
    return sprintf("%02X", (int) $x);
}

function assemble(&$opcodes, $org, &$errstr)
{
    
    $c = count($opcodes);
    $addr = $org;
    
    for($i = 0; $i < $c; ++$i)
    {
	$line =& $opcodes[$i];
	$opcodes[$i]['address'] = $addr;
	$addr = ( isset($line['org']) ? $line['org'] : $addr + $line['size']);
	
    }
    
    $addr = $org;
    for($i = 0; $i < $c; ++$i)
    {
	$line =& $opcodes[$i];
	//$addr += $line['size'];
	$addr = ( isset($line['org']) ? $line['org'] : $addr + $line['size']);
	
	if ( isset($line['org']) ) continue;
    
	if ( $line['size'] > 0 )
	{
	    if ( isset($line['label']) ) // branch or jsr/jmp instruction, so check labels
	    {
		for($l = 0; $l < $c; ++$l)
		{
		    $lab =& $opcodes[$l];
		    if ( $lab['size'] == 0 ) // label
		    {
			if ( $line['label'] == $lab['name'] ) // found label
			{
			    if ( !$line['long_label'] )		// branch
			    {
				$jump = 0;
				$org = 0;
				if ( $l > $i ) // forward label
				{
				    //echo "$l, $i;";
				    //break;
				    
				    for($p = $i + 1; $p < $l; ++$p)
				    {
					$org |= isset($opcodes[$p]['org']);
					$jump += $opcodes[$p]['size'];
				    }
				}
				else	// backward label
				{
				    //echo "r: $l, $i;";
				    //break;
				    
				    for($p = $l; $p < $i; ++$p)
				    {
					$org |= isset($opcodes[$p]['org']);
					$jump -= $opcodes[$p]['size'];
				    }
				}
				
				//echo "<br/>{$line['text']}: $jump<br/>\n";
				
				if ( abs($jump) >= 128 || $org )
				{
				    $errstr = "Branch too long or between segments at line {$line['line']} ('{$line['text']}')";
				    return false;
				}
				
				$opcodes[$i]['code'][] = ( $jump >= 0 ? $jump : 254 + $jump );
				break;
			    }
			    else	// jmp / jsr
			    {
				$jump = $lab['address'];
				
				$hi = 0; $lo = 0;
				addr("$" . dechex($jump), $hi, $lo);
				$opcodes[$i]['code'][] = $lo;
				$opcodes[$i]['code'][] = $hi;
				break;
			    }
			}
		    }
		}
		
		if ( $l == $c )
		{
		    // label not found
		    $errstr = "Label not found at line {$line['line']} ('{$line['text']}')";
		    return false;
		}
	    }
	}
    }
    return true;
}

?>

</body>
</html>