<?php

class Export
{
  private $currencyFormat = '#,#0.## \€;[Red]-#,#0.## \€';
  private $numberFormat = '#,#0.##;[Red]-#,#0.##';

  private $singleColumns = ['Location', 'Date', 'Time start', 'Time end', 'Hours', 'Description', 'Task type', 'Module', 'Ticket'];
  private $summaryColumns = ['Ticket', 'Description', 'Task type'];

  // prepare excel object and set defaults
  private function getExcel ()
  {
    $excel = new PHPExcel();
    $excel->getDefaultStyle()->getFont()->setName('Calibri');
    $excel->getDefaultStyle()->getFont()->setSize(10);

    $excel->getProperties()->setCreator("SmartiTracker")
      ->setLastModifiedBy("SmartiTracker")
      ->setTitle("SmartiTracker Hours Summary")
      ->setSubject("SmartiTracker Hours Summary")
      ->setKeywords("timetracker smartitracker");

    return $excel;
  }

  private function writeExcel ($excel)
  {
    $writer = PHPExcel_IOFactory::createWriter($excel, "Excel2007");

    $writer->setPreCalculateFormulas(true);

    $filename = 'summary-' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header("'Cache-Control: max-age=0'");

    $writer->save('php://output');
  }

  public function generateSingle ($data)
  {
    $excel = $this->getExcel();

    $sheet = $excel->getActiveSheet();
    $sheet->setTitle('Summary');

    $row = 2;
    $col = 1;

    $this->generateMonth($sheet, $col, $row, $data);

    // output excel to stream
    $this->writeExcel($excel);
  }

  public function generateAllMonths ($months)
  {
    $excel = $this->getExcel();

    $sheet = $excel->getActiveSheet();

    $first = true;
    foreach ($months as $month) {
      if (! $first)
        $sheet = $excel->createSheet(0);

      // prepare data
      $y = $month['Year'];
      $m = $month['Month'];
      $data = $month['Data'];

      // set title [MonthName Year]
      $dateObj = DateTime::createFromFormat('!m', $m);
      $monthName = $dateObj->format('F'); // March
      $name = $monthName . ' ' . $y;

      $sheet->setTitle($name);

      // initial position
      $row = 2;
      $col = 1;

      $this->generateMonth($sheet, $col, $row, $data);

      $first = false;
    }

    // output excel to stream
    $this->writeExcel($excel);
  }

  public function generateAllUsers ($users)
  {
    $excel = $this->getExcel();

    $sheet = $excel->getActiveSheet();
    $sheet->setTitle('Summary');

    $this->generateSummaryUser($sheet, $users);

    foreach ($users as $user) {
      $months = $user['MonthData'];
      $user = $user['User'];

      $sheet = $excel->createSheet();
      $sheet->setTitle($user['Name']);

      // initial position
      $row = 2;
      $col = 1;

      foreach ($months as $month) {
        // prepare data
        $y = $month['Year'];
        $m = $month['Month'];
        $data = $month['Data'];

        // output month cell
        $dateObj = DateTime::createFromFormat('!m', $m);
        $monthName = $dateObj->format('F'); // March
        $name = $y . ' ' . $monthName;

        $sheet->getCellByColumnAndRow($col, $row)->setValueExplicit($name);
        $sheet->getStyleByColumnAndRow($col, $row)->getFont()->setBold(true)->setSize(12);

        $row += 2;

        $this->generateMonth($sheet, $col, $row, $data);

        $row += 1;
      }
    }

    $excel->setActiveSheetIndexByName('Summary');

    // output excel to stream
    $this->writeExcel($excel);
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param array $users
   */
  private function generateSummaryUser ($sheet, $users)
  {
    $prepared = $this->prepareSummaryData($users);

    // prepared structured data [Module / TaskType / Ticket / User ]
    $preparedData = $prepared['PreparedData'];

    // array of user data only
    $userData = $prepared['UserData'];

    // prepare columns - basic couple + one for each user
    $columns = $this->summaryColumns;

    foreach ($userData as $user)
      $columns[] = $user['Name'];

    // additional one for sum
    $columns[] = 'SUM';

    // ****************
    // start generating

    // initial position
    $initialRow = 2;
    $initialCol = 1;
    $row = $initialRow;
    $col = $initialCol;

    // first titles on top of table
    $this->addTitles($sheet, $columns, $col, $row);
    $row += 2;

    foreach ($preparedData as $moduleName => $module) {
      $this->generateSummaryModule($sheet, $moduleName, $module, $userData, $col, $row);

    }

    // summary of all hours
    $this->setModuleFinalCalculation($sheet, $initialCol, $initialRow, $userData);

    $this->setAutoSize($sheet);

    // set description to limited size
    $descriptionCol = $sheet->getColumnDimension('C');
    $descriptionCol->setAutoSize(false);
    $descriptionCol->setWidth(60);

    $startColHour = $col + 3;
    $endColHour = $startColHour + sizeof($userData);
    for ($i = $startColHour; $i <= $endColHour; $i ++) {
      $col = $sheet->getColumnDimensionByColumn($i);
      $col->setAutoSize(false);
      $col->setWidth(10);
    }
  }

  /**
   * Summary for single module
   *
   * @param PHPExcel_Worksheet $sheet
   * @param string $moduleName
   * @param array $module
   * @param array $userData
   * @param int $col
   * @param int $row
   */
  private function generateSummaryModule ($sheet, $moduleName, $module, $userData, &$col, &$row)
  {
    $withoutTickets = [];

    $cellData = [];
    $comments = [];
    $ticketRow = 0;
    foreach ($module as $taskType => $tickets) {
      // remove tracks without ticket ID, we'll add them at the end
      if (isset($tickets['!NoTicket'])) {
        foreach ($tickets['!NoTicket'] as $ticket)
          $withoutTickets[] = $ticket;

        unset($tickets['!NoTicket']);
      }

      // let's put tickets in order
      ksort($tickets);

      foreach ($tickets as $ticketID => $ticket) {
        $dataRow = [
          '#' . $ticketID,
          $ticket['Description'],
          $taskType
        ];

        $users = $ticket['Users'];
        foreach ($userData as $userID => $user) {
          $hours = isset($users[$userID]) ? $users[$userID] : 0;
          $dataRow[] = $hours;
        }

        // NOTE: summary cell with be calculated with formula

        $cellData[] = $dataRow;

        if (! empty($ticket['Comments'])) {
          $comments[$ticketRow] = $ticket['Comments'];
        }

        $ticketRow ++;
      }
    }

    // lets add tickets without number
    foreach ($withoutTickets as $ticket) {
      $dataRow = [
        null,
        $ticket['Description'],
        $taskType
      ];

      foreach ($userData as $userID => $user) {
        $hours = $userID == $ticket['UserID'] ? $ticket['Hours'] : 0;
        $dataRow[] = $hours;
      }

      // NOTE: summary cell with be calculated with formula

      $cellData[] = $dataRow;
    }

    // output module name
    $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($moduleName);
    $sheet->getStyleByColumnAndRow($col + 1, $row)->getFont()->setBold(true);
    $row ++;

    // output all data
    $startCell = $sheet->getCellByColumnAndRow($col, $row)->getCoordinate();
    $sheet->fromArray($cellData, null, $startCell);

    // append comments to cells
    foreach ($comments as $rowOffset => $comment) {
      foreach ($comment as $text) {
        $sheet->getCommentByColumnAndRow($col + 1, $row + $rowOffset)->getText()->createTextRun($text);
        $sheet->getCommentByColumnAndRow($col + 1, $row + $rowOffset)->getText()->createTextRun("\r\n");
      }
    }

    // borders, alignment and data format
    $this->addTableBorders($sheet, $col, $row, $cellData);
    $this->setModuleAlignment($sheet, $col, $row, $cellData, $userData);
    $this->setModuleFormat($sheet, $col, $row, $cellData, $userData);

    // insert formulas
    $this->setModuleCalculation($sheet, $col, $row, $cellData, $userData);

    $row += sizeof($cellData) + 1;
  }

  private function prepareSummaryData ($users)
  {
    // prepared structured data [Module / TaskType / Ticket / User ]
    $preparedData = [];

    // array of user data only
    $userData = [];

    $userOffset = 0;
    foreach ($users as $user) {
      $months = $user['MonthData'];
      $user = $user['User'];

      // fill user data
      $userID = $user['UserID'];
      $user['Offset'] = $userOffset ++; // for correct cell positioning
      $userData[$userID] = $user;

      foreach ($months as $month) {
        $tracks = $month['Data'];

        foreach ($tracks as $track) {
          $module = $track['Module'];
          $taskType = $track['TaskType'];
          $ticket = $track['Ticket'];

          // calculate user hours
          $timeStart = $track['TimeStart'];
          $timeEnd = $track['TimeEnd'];

          $time = strtotime($timeEnd) - strtotime($timeStart);
          $hours = $time / 60 / 60;

          if (! isset($preparedData[$module]))
            $preparedData[$module] = [];

          if (! isset($preparedData[$module][$taskType]))
            $preparedData[$module][$taskType] = [];

          if ($ticket) {
            if (! isset($preparedData[$module][$taskType][$ticket]))
              $preparedData[$module][$taskType][$ticket] = [
                'Description' => $track['Description'],
                'Comments' => [],
                'Users' => []
              ];

            if ($preparedData[$module][$taskType][$ticket]['Description'] != $track['Description']) {
              if (! in_array($track['Description'], $preparedData[$module][$taskType][$ticket]['Comments']))
                $preparedData[$module][$taskType][$ticket]['Comments'][] = $track['Description'];
            }

            if (! isset($preparedData[$module][$taskType][$ticket]['Users'][$userID]))
              $preparedData[$module][$taskType][$ticket]['Users'][$userID] = 0;

            $preparedData[$module][$taskType][$ticket]['Users'][$userID] += $hours;
          }
          else {
            if (! isset($preparedData[$module][$taskType]['!NoTicket']))
              $preparedData[$module][$taskType]['!NoTicket'] = [];

            $preparedData[$module][$taskType]['!NoTicket'][] = [
              'Description' => $track['Description'],
              'UserID' => $userID,
              'Hours' => $hours
            ];
          }
        }
      }
    }

    return [
      'PreparedData' => $preparedData,
      'UserData' => $userData
    ];
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param array $data
   */
  private function generateMonth ($sheet, &$col, &$row, $data)
  {
    // first titles on top of table
    $this->addTitles($sheet, $this->singleColumns, $col, $row);

    $tracks = self::prepareTracksData($data);

    // skip rows
    $row += 2;

    // output track data
    $startCell = $sheet->getCellByColumnAndRow($col, $row)->getCoordinate();
    $sheet->fromArray($tracks, null, $startCell);

    // hour formulas
    $this->setTracksCalculation($sheet, $col, $row, $tracks);

    // set alignment
    $this->setTracksAlignment($sheet, $col, $row, $tracks);

    // proper formatting
    $this->setTracksFormat($sheet, $col, $row, $tracks);

    // add borders
    $this->addTableBorders($sheet, $col, $row, $tracks);

    $this->setAutoSize($sheet);

    // set description to limited size
    $descriptionCol = $sheet->getColumnDimension('G');
    $descriptionCol->setAutoSize(false);
    $descriptionCol->setWidth(45);

    // reset cell position
    $sheet->setSelectedCell();

    $row += sizeof($data) + 3; // 3 for summary field
  }

  /**
   * Set all columnts auto size
   *
   * @param PHPExcel_Worksheet $sheet
   */
  private function setAutoSize ($sheet)
  {
    // resize columns to match content
    foreach (range('A', $sheet->getHighestDataColumn()) as $colIndex) {
      $sheet->getColumnDimension($colIndex)->setAutoSize(true);
    }
  }

  private function addTitles ($sheet, $titles, $col, $row)
  {
    $count = 0;
    foreach ($titles as $column) {
      $sheet->setCellValueByColumnAndRow($col + ($count ++), $row, $column);
    }

    $titlesRange = $this->getRange($sheet, $col, $row, $col + sizeof($titles) - 1, $row);
    $sheet->getStyle($titlesRange)->getFont()->setBold(true);

    $this->setColumnAlignment($sheet, $col, $row, sizeof($titles), 1, 'center');
  }

  private function getRange ($sheet, $colStart, $rowStart, $colEnd, $rowEnd)
  {
    $startCell = $sheet->getCellByColumnAndRow($colStart, $rowStart)->getCoordinate();
    $endCell = $sheet->getCellByColumnAndRow($colEnd, $rowEnd)->getCoordinate();

    return $startCell . ':' . $endCell;
  }

  /**
   * Add thin borders with thick outside
   *
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param array $data
   */
  private function addTableBorders ($sheet, $col, $row, $data)
  {
    // NOTE: gotta call getStyle every time, cause when border style is applied, cell range deselects

    $dataRange = $this->getRange($sheet, $col, $row, $col + sizeof($data[0]) - 1, $row + sizeof($data) - 1);

    // inner borders
    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);

    // outside borders
    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getBorders()->getLeft()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);

    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getBorders()->getRight()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);

    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);

    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param int $width
   * @param int $height
   * @param string $align
   * @param bool $wrap
   */
  private function setColumnAlignment ($sheet, $col, $row, $width, $height, $align = 'left', $wrap = false)
  {
    $dataRange = $this->getRange($sheet, $col, $row, $col + $width - 1, $row + $height - 1);

    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getAlignment()->setHorizontal($align)->setVertical('top');

    if ($wrap) {
      $dataRangeStyles = $sheet->getStyle($dataRange);
      $dataRangeStyles->getAlignment()->setWrapText(true);
    }
  }

  private function  setTracksAlignment ($sheet, $col, $row, $tracks)
  {
    $trackCount = sizeof($tracks);

    // center from location to hours - 5 columns
    $this->setColumnAlignment($sheet, $col, $row, 5, $trackCount, 'center');

    // wrap description
    $this->setColumnAlignment($sheet, $col + 5, $row, 1, $trackCount, 'left', true);

    // center tasktype, module and ticket
    $this->setColumnAlignment($sheet, $col + 6, $row, 3, $trackCount, 'center');

    // sum of hours and label
    $this->setColumnAlignment($sheet, $col + 4, $row + $trackCount + 1, 1, 1, 'center');
    $this->setColumnAlignment($sheet, $col + 3, $row + $trackCount + 1, 1, 1, 'right');
  }

  private function  setTracksFormat ($sheet, $col, $row, $tracks)
  {
    $trackCount = sizeof($tracks);

    // date
    $this->setColumnFormat($sheet, $col + 1, $row, 1, $trackCount, 'date');

    // hours
    $this->setColumnFormat($sheet, $col + 2, $row, 2, $trackCount, 'hour');

    // calculated hours
    $this->setColumnFormat($sheet, $col + 4, $row, 2, $trackCount, 'number');

    // sum of calculated hours
    $this->setColumnFormat($sheet, $col + 4, $row + $trackCount + 1, 2, 1, 'number');
  }


  private function  setModuleAlignment ($sheet, $col, $row, $tickets, $userData)
  {
    $ticketCount = sizeof($tickets);

    // center ticket id
    $this->setColumnAlignment($sheet, $col, $row, 1, $ticketCount, 'center');

    // wrap description
    $this->setColumnAlignment($sheet, $col + 1, $row, 1, $ticketCount, 'left', true);

    // center task type
    $this->setColumnAlignment($sheet, $col + 2, $row, 2 + sizeof($userData), $ticketCount, 'center');
  }

  private function  setModuleFormat ($sheet, $col, $row, $tickets, $userData)
  {
    $ticketCount = sizeof($tickets);

    // calculated hours
    $this->setColumnFormat($sheet, $col + 3, $row, sizeof($userData) + 1, $ticketCount, 'number');
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param array $tickets
   * @param array $userData
   */
  private function  setModuleCalculation ($sheet, $col, $row, $tickets, $userData)
  {
    $firstColumn = $col + 3;

    for ($i = 0; $i < sizeof($tickets); $i ++) {
      $startCell = $sheet->getCellByColumnAndRow($firstColumn, $row + $i)->getCoordinate();
      $endCell = $sheet->getCellByColumnAndRow($firstColumn + sizeof($userData) - 1, $row + $i)->getCoordinate();

      $formula = '=SUM(' . $startCell . ':' . $endCell . ')';
      $cell = $sheet->getCellByColumnAndRow($firstColumn + sizeof($userData), $row + $i);
      $cell->setValue($formula);
    }
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param array $userData
   */
  private function setModuleFinalCalculation ($sheet, $col, $row, $userData)
  {
    $firstColumn = $col + 3;

    $lastRow = $sheet->getHighestDataRow();

    for ($i = 0; $i < sizeof($userData); $i ++) {
      $startCell = $sheet->getCellByColumnAndRow($firstColumn + $i, $row)->getCoordinate();
      $endCell = $sheet->getCellByColumnAndRow($firstColumn + $i, $lastRow)->getCoordinate();

      $formula = '=SUM(' . $startCell . ':' . $endCell . ')';

      $sheet->getCellByColumnAndRow($firstColumn + $i, $lastRow + 2)->setValue($formula);
    }

    $this->setColumnFormat($sheet, $firstColumn, $lastRow + 2, 1, sizeof($userData), 'number');

    // label cell
    $labelCell = $sheet->getCellByColumnAndRow($firstColumn - 1, $lastRow + 2);
    $labelCell->setValue("Sum total:");

    $this->setColumnAlignment($sheet, $firstColumn - 1, $lastRow + 2, 1, 1, 'right');
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param array $tracks
   */
  private function  setTracksCalculation ($sheet, $col, $row, $tracks)
  {
    $hourColumn = $col + 4;

    for ($i = 0; $i < sizeof($tracks); $i ++) {
      $timeStartCell = $sheet->getCellByColumnAndRow($hourColumn - 2, $row + $i)->getCoordinate();
      $timeEndCell = $sheet->getCellByColumnAndRow($hourColumn - 1, $row + $i)->getCoordinate();

      $formula = '=(' . $timeEndCell . '-' . $timeStartCell . ') * 24';
      $cell = $sheet->getCellByColumnAndRow($hourColumn, $row + $i);
      $cell->setValue($formula);
    }

    $sumCellOffset = $row + $i - 1;
    $startCell = $sheet->getCellByColumnAndRow($hourColumn, $row)->getCoordinate();
    $endCell = $sheet->getCellByColumnAndRow($hourColumn, $sumCellOffset)->getCoordinate();

    $formula = '=SUM(' . $startCell . ':' . $endCell . ')';

    $sheet->getCellByColumnAndRow($hourColumn, $sumCellOffset + 2)->setValue($formula);

    // label cell
    $labelCell = $sheet->getCellByColumnAndRow($hourColumn - 1, $sumCellOffset + 2);
    $labelCell->setValue("Sum:");
  }

  /**
   * @param PHPExcel_Worksheet $sheet
   * @param int $col
   * @param int $row
   * @param int $width
   * @param int $height
   * @param string $format
   */
  private function setColumnFormat ($sheet, $col, $row, $width, $height, $format = 'text')
  {
    $dataRange = $this->getRange($sheet, $col, $row, $col + $width - 1, $row + $height - 1);

    $excelFormat = PHPExcel_Style_NumberFormat::FORMAT_GENERAL;
    if ($format == 'number')
      $excelFormat = PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00;
    else if ($format == 'hour')
      $excelFormat = PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME3;
    else if ($format == 'date')
      $excelFormat = 'dd.mm.yyyy';


    $dataRangeStyles = $sheet->getStyle($dataRange);
    $dataRangeStyles->getNumberFormat()->setFormatCode(
      $excelFormat
    );
  }

  private static function prepareTracksData ($tracks)
  {
    $preparedTracks = [];

    // we write only first date in day
    $lastDate = null;

    // add them by correct order
    foreach ($tracks as $track) {
      $prepared = [];

      // first prepare some data
      $start = strtotime($track['TimeStart']);
      $end = strtotime($track['TimeEnd']);

      $date = date('d.m.Y', $start);
      $sameDay = $date == $lastDate;

      // for next loop
      $lastDate = $date;

      // simple string
      $prepared['Location'] = $track['Location'];


      $prepared['Date'] = ! $sameDay ? $date : null;
      $prepared['TimeStart'] = PHPExcel_Shared_Date::PHPToExcel($start); // date('d.m.Y H:i', $start);
      $prepared['TimeEnd'] = PHPExcel_Shared_Date::PHPToExcel($end); //date('d.m.Y H:i', $end);

      // lets leave this empty, we'll add calculation later
      $prepared['Hours'] = null;

      // simple strings
      $prepared['Description'] = $track['Description'];
      $prepared['TaskType'] = $track['TaskType'];
      $prepared['Module'] = $track['Module'];

      // we add hash to ticket
      $ticket = $track['Ticket'];
      $prepared['Ticket'] = $ticket ? ('#' . $ticket) : null;

      $preparedTracks[] = $prepared;
    }

    return $preparedTracks;
  }
}