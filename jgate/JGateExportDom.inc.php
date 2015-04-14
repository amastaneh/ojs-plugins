<?php

/**
 * @file plugins/importexport/crossref/JGateExportDom.inc.php
 *
 * Copyright (c) 2013 Rodrigo De la Garza
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JGateExportDom
 * @ingroup plugins_importexport_jgate
 *
 * @brief JGate XML export plugin DOM functions
 */

// $Id$


import('lib.pkp.classes.xml.XMLCustomWriter');

//define('JGATE_XMLNS_XSI' , 'http://www.w3.org/2001/XMLSchema-instance');
//define('JGATE_VERSION' , '4.3.0');

class JGateExportDom {

	/**
	 * Build article XML using DOM elements
	 * @return XMLNode
	 */
	function &generateJGateDom() {
		// create the output XML document in DOM with a root node
		$doc =& XMLCustomWriter::createDocument();
		return $doc;
	}

	/**
	 * Generate DOI batch DOM tree.
	 * @param $doc object
	 * @return XMLNode
	 */
	function &generateDoiBatchDom(&$doc) {

		// Generate the root node for the file first and set its attributes
		$root =& XMLCustomWriter::createElement($doc, 'articles');

		/* Root doi_batch tag attributes
		 * Change to these attributes must be accompanied by a review of entire output
		 */
		//XMLCustomWriter::setAttribute($root, 'xmlns:xsi', JGATE_XMLNS_XSI);
		
		//XMLCustomWriter::setAttribute($root, 'version', JGATE_VERSION);
		
		XMLCustomWriter::appendChild($doc, $root);

		return $root;
	}


	/**
	 * Generate depositor node
	 * @param $doc XMLNode
	 * @param $name string
	 * @param $email string
	 * @return XMLNode
	 */
	function &generateDepositorDom(&$doc, $name, $email) {
		$depositor =& XMLCustomWriter::createElement($doc, 'depositor');
		XMLCustomWriter::createChildWithText($doc, $depositor, 'name', $name);
		XMLCustomWriter::createChildWithText($doc, $depositor, 'email_address', $email);

		return $depositor;
	}


	/**
	 * Generate the article node (the heart of the file).
	 * @param $doc XMLNode
	 * @param $journal Journal
	 * @param $issue Issue
	 * @param $section Section
	 * @param $article Article
	 * @return XMLNode
	 */
	function &generateArticleDom(&$doc, &$journal, &$issue, &$section, &$article) {
		// Create the base node
		$articleNode =& XMLCustomWriter::createElement($doc, 'article');
		//XMLCustomWriter::setAttribute($articleNode, 'publication_type', 'full_text');

		/* Titles */
		$titlesNode =& XMLCustomWriter::createElement($doc, 'ArtTitle');
		$titlesTextNode =&XMLCustomWriter::createTextNode($doc, $article->getLocalizedTitle());
		XMLCustomWriter::appendChild($titlesNode, $titlesTextNode);
		XMLCustomWriter::appendChild($articleNode, $titlesNode);

		/* The registrant is assumed to be the Publishing institution */
		$publisherInstitution = $journal->getSetting('publisherInstitution');
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'PubName', $publisherInstitution);

		/* Full Title of Journal */
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'JournalName', $journal->getLocalizedTitle());

		/* Both ISSNs are permitted for CrossRef, so sending whichever one (or both) */
		if ( $ISSN = $journal->getSetting('onlineIssn') ) {
			$onlineISSN =& XMLCustomWriter::createChildWithText($doc, $articleNode, 'EISSN', $ISSN);
		}

		/* Both ISSNs are permitted for CrossRef so sending whichever one (or both) */
		if ( $ISSN = $journal->getSetting('printIssn') ) {
			$printISSN =& XMLCustomWriter::createChildWithText($doc, $articleNode, 'PISSN', $ISSN);
		}

		/* Generate publication date */
		if ($issue->getDatePublished()) {
			$publicationDateNode =& JGateExportDom::generatePublisherDateDom($doc, $issue->getDatePublished());
			XMLCustomWriter::appendChild($articleNode, $publicationDateNode);
		}

		/* Journal volume */
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'volume', $issue->getVolume());

		/* Journal Issue */
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'issue', $issue->getNumber());

		/* Supplementary files */
		$textSUP = 'No';
		if ($article->getSuppFiles())
		{
			$textSUP = 'Yes';
		}

		XMLCustomWriter::createChildWithText($doc, $articleNode, 'SupIssue', $textSUP);

		/* publication date */
		$parsedPubdate = strtotime($issue->getDatePublished());
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'Pubdate', date('d-m-Y', $parsedPubdate));

		/* Authors */
		$AuthorsAffiNode =& JGateExportDom::generateAuthorsDom(&$doc, $articleNode, $article->getAuthors());
		XMLCustomWriter::appendChild($articleNode, $AuthorsAffiNode);
		

		/* publisher_item is the article pages */
		if ($article->getPages() != '') {
			XMLCustomWriter::createChildWithText($doc, $articleNode, 'PageNo', $article->getPages());
		}

		/* Generate DOI */
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'DOI', '');


		/* Article Keywords TODO*/
		$subjects = array_map('trim', explode(';', $article->getLocalizedSubject()));

		$keywords = '';
		foreach ($subjects as $key => $keyword) {
			$keywords .= $keyword;
			if(($key+1) != count($subjects))
			{
				$keywords .= ';';
			}
		}
		XMLCustomWriter::createChildWithText($doc, $articleNode, 'Keywords', $keywords, false);
		
		/* --- Abstract --- */
		if ($article->getLocalizedAbstract()) {
			$abstractNode = XMLCustomWriter::createChildWithText($doc, $articleNode, 'Abstract', strip_tags($article->getLocalizedAbstract()), false);
		}


		/* URL */
		$URLsNode =& XMLCustomWriter::createElement($doc, 'URLs');
		XMLCustomWriter::createChildWithText($doc, $URLsNode, 'abstract', Request::url(null, 'article', 'view', $article->getId()));

		$fulltextNode =& XMLCustomWriter::createElement($doc, 'Fulltext');
		XMLCustomWriter::createChildWithText($doc, $fulltextNode, 'pdf', Request::url(null, 'article', 'download', array($article->getId(),'pdf')));

		XMLCustomWriter::appendChild($URLsNode, $fulltextNode);
		XMLCustomWriter::appendChild($articleNode, $URLsNode);


		return $articleNode;
	}


	/**
	 * Generate publisher date - order matters
	 * @param $doc XMLNode
	 * @param $pubdate string
	 * @return XMLNode
	 */
	function &generatePublisherDateDom(&$doc, $pubdate) {
		$publicationDateNode =& XMLCustomWriter::createElement($doc, 'pub-date');
		XMLCustomWriter::setAttribute($publicationDateNode, 'pub-type', 'epub');

		$parsedPubdate = strtotime($pubdate);
		XMLCustomWriter::createChildWithText($doc, $publicationDateNode, 'day', date('d', $parsedPubdate), false);
		XMLCustomWriter::createChildWithText($doc, $publicationDateNode, 'month', date('m', $parsedPubdate), false);
		XMLCustomWriter::createChildWithText($doc, $publicationDateNode, 'year', date('Y', $parsedPubdate));

		return $publicationDateNode;
	}
	
	/**
	 * Generate Authors
	 * @param $doc XMLNode
	 * @param $authors object
	 * @return XMLNode
	 */
	function &generateAuthorsDom(&$doc, $articleNode, $authors) {
		$authors_obj = $authors;
		$authors_arr = array();
		//$corrFlag = false;
		$arr_affi = array();
		/*
		 * firts do authors
		 */
		foreach ($authors_obj as $index => $author)
		{
			$author_Salut = $author->getSalutation();
			$author_Fname = $author->getFirstName();
			$author_Lname = $author->getLastName();
			$author_id = $author->getAuthorID();
			
			$author_indexes_arr = array();
			
			$one_affi = false;
		
			$affiliations = $author->getAffiliation(null);
			
			if (is_array($affiliations)) foreach ($affiliations as $locale => $affiliation) {
				
				$curr_affis = explode("\\", $affiliation);				
				$curr_affis = array_filter($curr_affis);
				$curr_affis = array_values($curr_affis);
				
				/*
				 * Only one affiliation
				 */
				if(count($curr_affis) == 1)
				{
					$one_affi = true;
					// one affiliation in the field
					/*
					 * If affiliation exist, get index to be placed to @author jos
					 * if not insert in array and get index
					 */
					if(count($arr_affi)==0)
					{
						$arr_affi[] = trim($affiliation);
						$author_indexes_arr[]='1';
					}
					else {
						foreach ($curr_affis as $curr_aff) {
														
							/*
							 * look if affiliation already exist
							 */
							$key = array_search(trim($curr_aff), $arr_affi);
							if($key === false){
							    $arr_affi[] = trim($curr_aff);
							    $author_indexes_arr[] = count($arr_affi);
							}
							else {
								 $author_indexes_arr[] = ($key+1);
							}							
						}
					}
				}
				else {
					/*
					 * more than one affiliation
					 */
					
					/*
					 * If affiliation exist, get index to be placed to @author jos
					 * if not insert in array and get index
					 */
					$key_index = 0;
					if(count($arr_affi)==0)
					{
						foreach ($curr_affis as $key => $curr_aff) {
							$arr_affi[] = trim($curr_aff);
							
							$author_indexes_arr[] = ($key+1);
						}
					}
					else
					{
						foreach ($curr_affis as $key => $curr_aff) {
													
							$key = array_search(trim($curr_aff), $arr_affi);

							if($key === false){
							    $arr_affi[] = trim($curr_aff);
							    $author_indexes_arr[] = count($arr_affi);
							}
							else {
								 $author_indexes_arr[] = ($key+1);
							}							
						}
					}
				}
			}
			
			//$author_text .= $author_name.'<sup>'.$author_indexes.'</sup>';
			$single_author = false;
		
			if(count($authors_obj) == 1 && count($author_indexes_arr) == 1)
			{
				$single_author=true;
			}
		
			if(count($authors_obj) >1 && $author->getPrimaryContact())
			{
				$corrFlag = true;
				$authors_arr[] = array('id'=>$author_id,'user'=>$author,'salut'=> $author_Salut, 'fname' => $author_Fname,'lname' => $author_Lname,'ref'=>$author_indexes_arr,'corr'=>'*');
			}
			else
			{
				/*
				 * Single author, if only one affi setr flag to not display affiliation number
				 */
				$authors_arr[] = array('id'=>$author_id,'user'=>$author,'salut'=> $author_Salut, 'fname' => $author_Fname,'lname' => $author_Lname,'ref'=>$author_indexes_arr);
			}
		}
		
		$authorGroupNode =& XMLCustomWriter::createElement($doc, 'AuthorGroup');
		if(count($authors_arr)>0)
		{
			foreach ($authors_arr as $author) {
				$user = $author['user'];
				$referenceIndex = $author['ref'];
				/*
				 *   <Author AffiliationIDS="Aff1" CorrespondingAffiliationID="Aff1">
                        <AuthorName DisplayOrder="Western">
                           <Prefix>PD Dr.</Prefix>
                           <GivenName>T.</GivenName>
                           <FamilyName>Kriebel</FamilyName>
                        </AuthorName>
                        <Contact>
                           <Email>tkriebe@gwdg.de</Email>
                        </Contact>
                     </Author>
				 */
				$authorNode =& XMLCustomWriter::createElement($doc, 'Author');
				
				/*
				 * Make author references
				 */
				if(count($referenceIndex)>0)
				{
					$reftext='';
					foreach ($referenceIndex as $key => $value) {
						if($key != 0)
						{
							$reftext.=' ';
						}
						$reftext .= 'Aff'.$value;
					}
					XMLCustomWriter::setAttribute($authorNode, 'AffiliationIDS', $reftext);
				}
				
				$authorNameNode =& XMLCustomWriter::createElement($doc, 'AuthorName');
				
				//XMLCustomWriter::createChildWithText($doc, $authorNameNode, 'Prefix', $author['salut']);
				XMLCustomWriter::createChildWithText($doc, $authorNameNode, 'GivenName', $author['fname']);
				XMLCustomWriter::createChildWithText($doc, $authorNameNode, 'FamilyName', $author['lname']);
				
				XMLCustomWriter::appendChild($authorNode, $authorNameNode);
				
				$contactNode =& XMLCustomWriter::createElement($doc, 'Contact');
				XMLCustomWriter::createChildWithText($doc, $contactNode, 'Email', $user->getEmail());
				
				XMLCustomWriter::appendChild($authorNode, $contactNode);
				
				XMLCustomWriter::appendChild($authorGroupNode, $authorNode);
			}
			
			/*
				 * set affiliations and corresponding affiliation
				 */
				//if(isset($author['corr']))
				//{
				//	XMLCustomWriter::setAttribute($authorNode, 'CorrespondingAffiliationID', JGATE_XMLNS_XSI);
				//}			
				foreach ($arr_affi as $key => $affi) {
					$affiliationNode =& XMLCustomWriter::createElement($doc, 'Affiliation');
					XMLCustomWriter::setAttribute($affiliationNode, 'ID', 'Aff'.($key+1));
					XMLCustomWriter::createChildWithText($doc, $affiliationNode, 'OrgName', $affi);
					XMLCustomWriter::appendChild($authorGroupNode, $affiliationNode);
				}
		}
		
		return $authorGroupNode;
	}
}

?>
