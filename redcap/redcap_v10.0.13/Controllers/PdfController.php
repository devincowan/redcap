<?php

class PdfController extends Controller
{
	// Render the PDF
	public function index()
	{
		PDF::output();
	}
}