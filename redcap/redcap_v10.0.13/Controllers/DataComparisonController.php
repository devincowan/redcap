<?php

class DataComparisonController extends Controller
{	
	// Render page
	public function index()
	{
		$this->render('HeaderProject.php', $GLOBALS);
		DataComparisonTool::renderPage();
		$this->render('FooterProject.php');
	}
}