<?php

class DataImportController extends Controller
{
	// Render Data Import Tool page
	public function index()
	{
		$this->render('HeaderProject.php', $GLOBALS);
		DataImport::renderDataImportToolPage();
		$this->render('FooterProject.php');
	}
	
	// Download import template CSV file
	public function downloadTemplate()
	{
		DataImport::downloadCSVImportTemplate();
	}
}