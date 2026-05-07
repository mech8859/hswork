<?php
/**
 * 簡易 Excel/CSV 讀取器（不需外部套件）
 * 支援 .xlsx 和 .csv
 */
class ExcelReader
{
    /**
     * 讀取檔案，回傳二維陣列（含標題行）
     * @param string $filePath
     * @param string|null $sheetName 指定 xlsx 內的工作表名稱（例如「考勤詳細」），null=第一張
     */
    public static function read($filePath, $sheetName = null)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return self::readCsv($filePath);
        }
        return self::readXlsx($filePath, $sheetName);
    }

    /**
     * 列出 xlsx 內的所有工作表名稱
     */
    public static function listSheets($filePath)
    {
        $names = array();
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return $names;
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if ($wbXml) {
            $doc = new DOMDocument();
            $doc->loadXML($wbXml);
            foreach ($doc->getElementsByTagName('sheet') as $s) {
                $names[] = $s->getAttribute('name');
            }
        }
        $zip->close();
        return $names;
    }

    /**
     * 讀取 CSV
     */
    private static function readCsv($filePath)
    {
        $rows = array();
        $handle = fopen($filePath, 'r');
        if (!$handle) return $rows;

        // 偵測 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = $line;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * 讀取 .xlsx（用 ZIP + XML 解析，不需 PhpSpreadsheet）
     */
    private static function readXlsx($filePath, $sheetName = null)
    {
        $rows = array();
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return $rows;

        // 讀取 shared strings
        $strings = array();
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ssDoc = new DOMDocument();
            $ssDoc->loadXML($ssXml);
            $siNodes = $ssDoc->getElementsByTagName('si');
            foreach ($siNodes as $si) {
                $text = '';
                $tNodes = $si->getElementsByTagName('t');
                foreach ($tNodes as $t) {
                    $text .= $t->textContent;
                }
                $strings[] = $text;
            }
        }

        // 解析 workbook.xml 與 rels，找指定 sheetName 對應的 sheet*.xml；找不到就退回 sheet1
        $sheetTarget = 'xl/worksheets/sheet1.xml';
        if ($sheetName !== null) {
            $wbXml = $zip->getFromName('xl/workbook.xml');
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($wbXml && $relsXml) {
                $wbDoc = new DOMDocument(); $wbDoc->loadXML($wbXml);
                $relsDoc = new DOMDocument(); $relsDoc->loadXML($relsXml);
                $rIdMap = array();
                foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                    $rIdMap[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
                }
                foreach ($wbDoc->getElementsByTagName('sheet') as $s) {
                    if ($s->getAttribute('name') === $sheetName) {
                        $rid = $s->getAttribute('r:id');
                        if (!$rid) {
                            // 處理 sheet 元素 namespace 寫法差異
                            foreach ($s->attributes as $attr) {
                                if ($attr->localName === 'id' && stripos($attr->name, 'r:') !== false) {
                                    $rid = $attr->value; break;
                                }
                            }
                        }
                        if ($rid && isset($rIdMap[$rid])) {
                            $target = ltrim($rIdMap[$rid], '/');
                            // workbook.xml 的 target 通常是 worksheets/sheet2.xml，前綴 xl/
                            $sheetTarget = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . $target;
                        }
                        break;
                    }
                }
            }
        }
        $sheetXml = $zip->getFromName($sheetTarget);
        if (!$sheetXml) { $zip->close(); return $rows; }

        $doc = new DOMDocument();
        $doc->loadXML($sheetXml);
        $rowNodes = $doc->getElementsByTagName('row');

        // 取得 styles 來偵測日期格式
        $dateFormats = self::getDateFormatIds($zip);

        foreach ($rowNodes as $rowNode) {
            $cells = array();
            $maxCol = 0;
            $cNodes = $rowNode->getElementsByTagName('c');
            foreach ($cNodes as $c) {
                $ref = $c->getAttribute('r');
                $colIdx = self::colToIndex($ref);
                if ($colIdx > $maxCol) $maxCol = $colIdx;

                $type = $c->getAttribute('t');
                $style = $c->getAttribute('s');
                $vNode = $c->getElementsByTagName('v');
                $val = ($vNode->length > 0) ? $vNode->item(0)->textContent : '';

                if ($type === 's') {
                    // shared string
                    $val = isset($strings[(int)$val]) ? $strings[(int)$val] : $val;
                } elseif ($type === '' && $style !== '' && in_array((int)$style, $dateFormats)) {
                    // Excel 日期序號轉換
                    $val = self::excelDateToString((float)$val);
                }

                $cells[$colIdx] = $val;
            }

            // 填充空欄
            $row = array();
            for ($i = 0; $i <= $maxCol; $i++) {
                $row[] = isset($cells[$i]) ? $cells[$i] : '';
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        $zip->close();
        return $rows;
    }

    /**
     * 取得日期格式的 style index
     */
    private static function getDateFormatIds($zip)
    {
        $ids = array();
        $stylesXml = $zip->getFromName('xl/styles.xml');
        if (!$stylesXml) return $ids;

        $doc = new DOMDocument();
        $doc->loadXML($stylesXml);

        // 取得 numFmts
        $datePatterns = array('yy', 'mm', 'dd', 'yyyy', 'date', 'y/m', 'm/d');
        $builtinDateFmts = array(14,15,16,17,18,19,20,21,22,27,28,29,30,31,32,33,34,35,36,45,46,47);

        $customDateFmts = array();
        $numFmts = $doc->getElementsByTagName('numFmt');
        foreach ($numFmts as $nf) {
            $code = strtolower($nf->getAttribute('formatCode'));
            foreach ($datePatterns as $p) {
                if (strpos($code, $p) !== false) {
                    $customDateFmts[] = (int)$nf->getAttribute('numFmtId');
                    break;
                }
            }
        }

        // 取得 cellXfs
        $xfs = $doc->getElementsByTagName('cellXfs');
        if ($xfs->length > 0) {
            $xfNodes = $xfs->item(0)->getElementsByTagName('xf');
            $idx = 0;
            foreach ($xfNodes as $xf) {
                $fmtId = (int)$xf->getAttribute('numFmtId');
                if (in_array($fmtId, $builtinDateFmts) || in_array($fmtId, $customDateFmts)) {
                    $ids[] = $idx;
                }
                $idx++;
            }
        }

        return $ids;
    }

    /**
     * Excel 日期序號轉 Y-m-d 字串
     */
    private static function excelDateToString($serial)
    {
        if ($serial < 1) return '';
        // Excel epoch: 1899-12-30 is day 0
        $unixBase = mktime(0, 0, 0, 12, 30, 1899);
        $ts = $unixBase + (int)$serial * 86400;
        return date('Y-m-d', $ts);
    }

    /**
     * 欄位參考轉索引 (A1 → 0, B1 → 1, AA1 → 26)
     */
    private static function colToIndex($ref)
    {
        $letters = preg_replace('/[0-9]/', '', $ref);
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $idx - 1;
    }

    /**
     * 銀行明細欄位自動對應
     */
    public static function mapBankColumns($header)
    {
        $map = array(
            'sys_number'       => null,
            'upload_number'    => null,
            'bank_account'     => null,
            'transaction_date' => null,
            'posting_date'     => null,
            'transaction_time' => null,
            'cash_transfer'    => null,
            'summary'          => null,
            'currency'         => null,
            'debit_amount'     => null,
            'credit_amount'    => null,
            'balance'          => null,
            'note'             => null,
            'transfer_account' => null,
            'bank_code'        => null,
            'counter_account'  => null,
            'remark'           => null,
            'description'      => null,
        );

        $mapping = array(
            '系統編號'   => 'sys_number',
            '上傳編號'   => 'upload_number',
            '銀行帳戶'   => 'bank_account',
            '交易日期'   => 'transaction_date',
            '記帳日'     => 'posting_date',
            '交易時間'   => 'transaction_time',
            '現轉別'     => 'cash_transfer',
            '摘要'       => 'summary',
            '幣別'       => 'currency',
            '支出金額'   => 'debit_amount',
            '存入金額'   => 'credit_amount',
            '餘額(原始)' => 'balance',
            '餘額'       => 'balance',
            '備註'       => 'note',
            '轉出入帳號' => 'transfer_account',
            '存匯代號'   => 'bank_code',
            '對方帳號'   => 'counter_account',
            '註記'       => 'remark',
            '對象說明'   => 'description',
        );

        foreach ($header as $i => $col) {
            $col = trim($col);
            if (isset($mapping[$col])) {
                $map[$mapping[$col]] = $i;
            }
        }

        return $map;
    }

    /**
     * 從 row 取值
     */
    public static function getVal($row, $colMap, $field)
    {
        if (!isset($colMap[$field]) || $colMap[$field] === null) return '';
        $idx = $colMap[$field];
        return isset($row[$idx]) ? trim($row[$idx]) : '';
    }

    /**
     * 解析日期格式
     */
    public static function parseDate($val)
    {
        if (empty($val)) return null;
        $val = trim($val);

        // 已經是 Y-m-d 格式
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

        // Y/m/d 格式
        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // 嘗試 strtotime
        $ts = strtotime($val);
        if ($ts !== false) return date('Y-m-d', $ts);

        return null;
    }
}
