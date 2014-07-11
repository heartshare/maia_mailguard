<?php
   /*
     * $Id: cache.php 1572 2011-07-02 01:34:50Z rjl $
     *
     * MAIA MAILGUARD LICENSE v.1.0
     *
     * Copyright 2004 by Robert LeBlanc <rjl@renaissoft.com>
     *                   David Morton   <mortonda@dgrmm.net>
     * All rights reserved.
     *
     * PREAMBLE
     *
     * This License is designed for users of Maia Mailguard
     * ("the Software") who wish to support the Maia Mailguard project by
     * leaving "Maia Mailguard" branding information in the HTML output
     * of the pages generated by the Software, and providing links back
     * to the Maia Mailguard home page.  Users who wish to remove this
     * branding information should contact the copyright owner to obtain
     * a Rebranding License.
     *
     * DEFINITION OF TERMS
     *
     * The "Software" refers to Maia Mailguard, including all of the
     * associated PHP, Perl, and SQL scripts, documentation files, graphic
     * icons and logo images.
     *
     * GRANT OF LICENSE
     *
     * Redistribution and use in source and binary forms, with or without
     * modification, are permitted provided that the following conditions
     * are met:
     *
     * 1. Redistributions of source code must retain the above copyright
     *    notice, this list of conditions and the following disclaimer.
     *
     * 2. Redistributions in binary form must reproduce the above copyright
     *    notice, this list of conditions and the following disclaimer in the
     *    documentation and/or other materials provided with the distribution.
     *
     * 3. The end-user documentation included with the redistribution, if
     *    any, must include the following acknowledgment:
     *
     *    "This product includes software developed by Robert LeBlanc
     *    <rjl@renaissoft.com>."
     *
     *    Alternately, this acknowledgment may appear in the software itself,
     *    if and wherever such third-party acknowledgments normally appear.
     *
     * 4. At least one of the following branding conventions must be used:
     *
     *    a. The Maia Mailguard logo appears in the page-top banner of
     *       all HTML output pages in an unmodified form, and links
     *       directly to the Maia Mailguard home page; or
     *
     *    b. The "Powered by Maia Mailguard" graphic appears in the HTML
     *       output of all gateway pages that lead to this software,
     *       linking directly to the Maia Mailguard home page; or
     *
     *    c. A separate Rebranding License is obtained from the copyright
     *       owner, exempting the Licensee from 4(a) and 4(b), subject to
     *       the additional conditions laid out in that license document.
     *
     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS
     * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
     * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
     * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
     * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
     * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
     * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
     * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
     * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
     * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
     * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
     *
     */
class MessageCache {
    
    var $type;
    var $dbh;
    var $dbtype;
    var $smarty;
    var $sortby;
    var $sort_order;
    var $select_stmt;
    
    var $confirmed = 0;
    var $reported = 0;
    var $deleted = 0;
    var $rescued = 0;
    var $resent = 0;
    
    function MessageCache($type, & $dbh, $dbtype, & $smarty) {
        $this->smarty =& $smarty;
        $this->dbh =& $dbh;
        $this->dbtype = $dbtype;
        $this->type = $type;
        $this->smarty->assign("cache_type", $type);
        $this->sortby= array();
    }
    
    function get_offset() {
        global $_GET;
        return isset($_GET["offset"]) ? $_GET["offset"] : 0;
    }
    
    function get_sort_field() {
        switch ($this->type) {
                case "ham": $field = "ham_cache_sort";
                    break;
                case "spam": $field = "spam_quarantine_sort";
                    break;
                case "virus": $field = "virus_quarantine_sort";
                    break;
                case "attachment": $field = "attachment_quarantine_sort";
                    break;
                case "header": $field = "header_quarantine_sort";
                    break;
                default: $field = "ham_cache_sort";
        }
        $this->sort_field = $field;
        return $field;
    
    }
    
    function get_sort_stmt() {
        switch ($this->type) {
            case "ham": $stmt = "maia_mail_recipients.type = 'H' ";
                    break;
            case "spam": $stmt = "maia_mail_recipients.type IN ('S','P') ";
                break;
            case "virus": $stmt = "maia_mail_recipients.type = 'V' ";
                break;
            case "attachment": $stmt = "maia_mail_recipients.type = 'F' ";
                break;
            case "header": $stmt = "maia_mail_recipients.type = 'B' ";
                break;
            default: $stmt = "maia_mail_recipients.type = 'H' ";
        }
        return $stmt;
    }
    
    function set_sort_order($sort, $euid, $msid) {
        global $_GET;
         $field = $this->get_sort_field();

         $sort = strtoupper(trim($_GET["sort"]));
         if (strlen($sort) == 2) {
             switch($sort[0]) {
                 case "X":
                 case "D":
                 case "F":
                 case "T":
                 case "S":
                     if ($sort[1] == "A" || $sort[1] == "D") {
                         $sth = $dbh->prepare("UPDATE maia_users SET ". $field ." = ? WHERE id = ?");
                         $sth->execute(array($sort, $euid));
                         if (PEAR::isError($sth)) {
                             die($sth->getMessage());
                         }
                         header("Location: list-cache.php" . $msid. "cache_type=". $this->type);
                         exit;
                     }
                     break;
                 default:
                     break;
             }
            
         }
    }
    
    function get_sort_order($euid) {
        global $dbh, $msid, $sid;
        $sort = strtoupper(get_user_value($euid, $this->get_sort_field()));
             switch($sort[0]) {
                 case "X":
                     $this->sortby['score'] = ($sort[1] == "A" ? "xd" : "xa"); //FIXME - depends on spam/ham/other
                     $this->sortby['column'] = "score";
                     $this->sortby['date'] = "da";
                     $this->sortby['from'] = "fa";
                     $this->sortby['to'] = "ta";
                     $this->sortby['subject'] = "sa";
                     break;
                 case "D":
                     $this->sortby['score'] = "xa"; //FIXME - depends on spam/ham/other
                     $this->sortby['column'] = "received_date";
                     $this->sortby['date'] = ($sort[1] == "A" ? "dd" : "da");
                     $this->sortby['from'] = "fa";
                     $this->sortby['to'] = "ta";
                     $this->sortby['subject'] = "sa";
                     break;
                 case "F":
                     $this->sortby['score'] = "xa"; //FIXME - depends on spam/ham/other
                     $this->sortby['column'] = "sender_email";
                     $this->sortby['date'] = "da";
                     $this->sortby['from'] = ($sort[1] == "A" ? "fd" : "fa");
                     $this->sortby['to'] = "ta";
                     $this->sortby['subject'] = "sa";
                     break;
                 case "S":
                     $this->sortby['score'] = "xa"; //FIXME - depends on spam/ham/other
                     $this->sortby['column'] = "subject";
                     $this->sortby['date'] = "da";
                     $this->sortby['from'] = "fa";
                     $this->sortby['to'] = "ta";
                     $this->sortby['subject'] = ($sort[1] == "A" ? "sd" : "sa");
                     break;
                 case "T":
                     $this->sortby['score'] = "xa"; //FIXME - depends on spam/ham/other
                     $this->sortby['column'] = "envelope_to";
                     $this->sortby['date'] = "da";
                     $this->sortby['from'] = "fa";
                     $this->sortby['to'] = ($sort[1] == "A" ? "td" : "ta");
                     $this->sortby['subject'] = "sa";
                     break;
                 default:
                     $this->sortby['score'] = "xa"; //FIXME - depends on spam/ham/other
                     $this->sortby['column'] = "score";
                     $this->sortby['date'] = "da";
                     $this->sortby['from'] = "fa";
                     $this->sortby['to'] = "ta";
                     $this->sortby['subject'] = "sa";
             }
             if ($sort[1] == "D") {
                 $this->sort_order = "DESC";
             } else {
                 $this->sort_order = "ASC";
             }
             $this->smarty->assign("sortby", $this->sortby);
             $this->smarty->assign("sort_order", $this->sort_order);
    }
    
    function set_select() {
        $this->select_count = "SELECT COUNT(maia_mail.id) as CNT " .
              "FROM maia_mail_recipients " .
              "LEFT JOIN maia_mail " .
              "ON maia_mail.id = maia_mail_recipients.mail_id " .
              "WHERE " . $this->get_sort_stmt() .
              "AND maia_mail_recipients.recipient_id = ? ";

        $this->select_stmt = "SELECT maia_mail.id, ";
        if (substr($this->dbtype,0,5) == "mysql") {

           $this->select_stmt .= "DATE_ADD(maia_mail.received_date, INTERVAL " . $_SESSION["clock_offset"] . " SECOND) AS received_date, ";

        } elseif ($this->dbtype == "pgsql") {

           $this->select_stmt .= "date_trunc('second', maia_mail.received_date + INTERVAL '" . $_SESSION["clock_offset"] . " SECOND') AS received_date, ";

        }
        $this->select_stmt .= "maia_mail.score, maia_mail.sender_email, maia_mail.subject, maia_mail.envelope_to " .
                              "FROM maia_mail_recipients " .
                              "LEFT JOIN maia_mail " .
                              "ON maia_mail.id = maia_mail_recipients.mail_id " .
                              "WHERE " . $this->get_sort_stmt() .
                              "AND maia_mail_recipients.recipient_id = ? " .
                              " ORDER BY maia_mail." . $this->sortby['column'] . " " . $this->sort_order;
    }
    
    function confirm_cache($euid) {    
      $message = "";

      
      $ham_list = array();
      $spam_list = array();
      $delete_list = array();
      $resend_list = array();
      
      global $_POST, $lang, $logger;
      if(isset($_POST['cache_item'])) {
          $items = $_POST['cache_item'];
      } else {
          $items=array();
      }
      foreach ($items as $type => $mail_item) {
        foreach ($mail_item as $mail_id => $value) {
          if ($type == "generic") {
              $newtype = $_POST['submit'];
          } else {
              $newtype = $value;
          }
          // report item
          if ($newtype == "spam") {
            switch ($this->type) {
              case 'ham':
                // Mark the item as false negative.   It will also be marked as confirmed.               
                record_mail_stats($euid, $mail_id, "fn");
                $this->reported++;
                break;
              default:
                $this->confirmed++;
            }
            array_push($spam_list, $mail_id);
            
          //send item
          } elseif ($newtype == "ham") {
            switch ($this->type) {
              case 'ham':
                array_push($ham_list, $mail_id);
                $this->confirmed++;
                break;
              default:
                $result = rescue_item($euid, $mail_id); // done individually because of mail delivery
                if (strncmp($result, "2", 1) == 0) {
                  $this->rescued++;
                } else {
                $message .= $result . "\n";                    
                }                      
            }              
          //delete item.
          } elseif ($newtype == "delete") {
              array_push($delete_list, $mail_id);
              $this->deleted++;
          // resend the item and leave it in the cache
          } elseif ($newtype == "resend") {
              array_push($resend_list, $mail_id);
             $this->resent++;
          }
        }
      }
      
      if (count($ham_list) > 0)      { confirm_ham($euid, $ham_list );   }
      if (count($spam_list) > 0)    { confirm_spam($euid, $spam_list);   }
      if (count($delete_list) > 0 ) { delete_mail_reference($euid, $delete_list); }
      if (count($resend_list) > 0 ) { resend_message($euid, $resend_list); }

      update_mail_stats($euid, "suspected_ham");
      if ($this->confirmed > 0) {
        switch($this->type) {
            case "ham":
              $message .= sprintf($lang['text_ham_confirmed'], $this->confirmed) . ".<br>";
              break;
            case "spam":
              $message .= sprintf($lang['text_spam_confirmed'], $this->confirmed) . ".<br>";
              break;
            default:
              $message .= sprintf($lang['text_messages_confirmed'], $this->confirmed) . ".<br>";
        }
      }

      if ($this->reported > 0) {
          $message .= sprintf($lang['text_spam_reported'], $this->reported) . ".<br>";
      }
      if ($this->deleted > 0) {
        switch($this->type) {
            case 'ham':
              $message .= sprintf($lang['text_ham_deleted'], $this->deleted) . ".<br>";
              break;
            case 'spam':
              $message .= sprintf($lang['text_spam_deleted'], $this->deleted) . ".<br>";
              break;
            case 'virus':
              $message .= sprintf($lang['text_viruses_deleted'], $this->deleted) . ".<br>";
              break;
            case 'attachment':
              $message .= sprintf($lang['text_attachments_deleted'], $this->deleted) . ".<br>";
              break;
            case 'header':
              $message .= sprintf($lang['text_headers_deleted'], $this->deleted) . ".<br>";
              break;
        }
      }
      if ($this->rescued > 0) {
          switch($this->type) {
              case 'spam':
                $message .= sprintf($lang['text_spam_rescued'], $this->rescued) . ".<br>";
                break;
              case 'virus':
                $message .= sprintf($lang['text_viruses_rescued'], $this->rescued) . ".<br>";
                break;
              case 'attachment':
                $message .= sprintf($lang['text_attachments_rescued'], $this->rescued) . ".<br>";
                break;
              case 'header':
                $message .= sprintf($lang['text_headers_rescued'], $this->rescued) . ".<br>";
                break;
          }
      }
      if ($this->resent > 0) {
        $message .= sprintf($lang['text_message_resent'], $this->resent) . ".<br>";
      }
      
      return $message;
    
    }
    
    function confirmed_actions($action) {
        switch ($action) {
            case "confirmed":
                return $this->confirmed;
            case 'reported':
                return $this->reported;
            case 'rescued':
                return $this->rescued;
            case 'deleted':
                return $this->deleted;
            case 'resent':
                return $this->resent;
        }
        
    }
    
    function render($euid) {
        global $lang, $sid, $msid, $offset, $message;
        $magic_quotes = get_magic_quotes_gpc();
        $nothing_to_show = true;
        $offset = 0;
        $this->smarty->assign("msid", $msid);
        $this->smarty->assign("lang", $lang);
        $this->smarty->assign("actionlang", response_text($this->type));
        $user_config = get_maia_user_row($euid);
		
		//set the class names for the given cache type, and default box to check.
		switch ($this->type) {
			case 'ham':
                $this->smarty->assign("banner_class", "hambanner");
				$this->smarty->assign("header_class", "hamheader");
				$this->smarty->assign("body_class", "hambody");
				$this->smarty->assign("alt_body_class", "hambody_alt");
				$this->smarty->assign("header_text", $lang['header_suspected_ham']);
                $this->smarty->assign("def_rb", "ham");
				break;
		    case 'spam': 
                $this->smarty->assign("banner_class", "suspected_spambanner");
				$this->smarty->assign("header_class", "suspected_spamheader");
				$this->smarty->assign("body_class", "suspected_spambody");
				$this->smarty->assign("alt_body_class", "suspected_spambody_alt");
				$this->smarty->assign("header_text", $lang['header_spam']);
                $this->smarty->assign("def_rb", "spam");
				break;
			case "virus": 
                $this->smarty->assign("banner_class", "virusbanner");
				$this->smarty->assign("header_class", "virusheader");
				$this->smarty->assign("body_class", "virusbody");
				$this->smarty->assign("alt_body_class", "virusbody_alt");
				$this->smarty->assign("header_text", $lang['header_viruses']);
                $this->smarty->assign("def_rb", "delete");
				break;
            case "attachment":  
                $this->smarty->assign("banner_class", "banned_filebanner");
				$this->smarty->assign("header_class", "banned_fileheader");
				$this->smarty->assign("body_class", "banned_filebody");
				$this->smarty->assign("alt_body_class", "banned_filebody_alt");
				$this->smarty->assign("header_text", $lang['header_banned_files']);
                $this->smarty->assign("def_rb", "delete");
				break;
            case "header":  
                $this->smarty->assign("banner_class", "bad_headerbanner");
				$this->smarty->assign("header_class", "bad_headerheader");
				$this->smarty->assign("body_class", "bad_headerbody");
				$this->smarty->assign("alt_body_class", "bad_headerbody_alt");
				$this->smarty->assign("header_text", $lang['header_bad_headers']);
                $this->smarty->assign("def_rb", "delete");
				break;
			
		}

        $sth3 = $this->dbh->prepare($this->select_count);
        $res3 = $sth3->execute(array($euid));
        if (PEAR::isError($sth3)) {
            die($sth3->getMessage());
        }
        $numRows = $res3->fetchRow();
        $sth3->free();

        if ($numRows['cnt'] > 0)
        {
            $sth2 = $this->dbh->prepare("SELECT email FROM users WHERE maia_user_id = ?");
            $res2 = $sth2->execute(array($euid));
            if (PEAR::isError($sth2)) {
                die($sth2->getMessage());
            }
            while ($row2 = $res2->fetchrow()) {
                $personal_addresses[] = $row2["email"];
            }
            $sth2->free();
            $personal_addresses = array_flip($personal_addresses);
            $domain_default = is_a_domain_default_user($euid);
            
            $need_to = (count($personal_addresses) > 1 || $domain_default);
            $this->smarty->assign("need_to", $need_to); //need to output the to: column
            
            $per_page = get_user_value($euid, "items_per_page");

            $this->smarty->assign("truncate_subject", $user_config["truncate_subject"] == 0 ? 10000 : $user_config["truncate_subject"] );
            $this->smarty->assign("truncate_email", $user_config["truncate_email"] == 0 ? 10000 : $user_config["truncate_email"] );

            
            $pagerOptions = array(
                                   'mode'    => 'Sliding',
                                   'delta'   => 5,
                                   'perPage' => $per_page,
                                   'totalItems' => $numRows,
                                   );

            $paged_data = Pager_Wrapper_DB($this->dbh, $this->select_stmt, $pagerOptions, null, MDB2_FETCHMODE_ASSOC, array($euid));
            //$paged_data['data'];  //paged data
            //$paged_data['links']; //xhtml links for page navigation
            //$paged_data['page_numbers']; //array('current', 'total');
            if (PEAR::isError($paged_data)) {
                $_SESSION["message"] = $paged_data->getMessage();
                header("Location: welcome.php" . $sid);
                exit;
            }   
             $maxid = 0;
             $nothing_to_show = false;
       
             $this->smarty->assign("data", $paged_data['data']);
             $this->smarty->assign("offset", $offset);
             //print_r($paged_data['page_numbers']);
             $this->smarty->assign("pages", $paged_data['page_numbers']);
        
            if ($numRows == 1) {
                $item_text = $lang['text_item'];
            } else {
                $item_text = $lang['text_items'];
            }

            $count = 0;
            $rows = array();
        
              foreach ($paged_data['data'] as $row)
              {
                if ($row["id"] > $maxid) {
                      $maxid = $row["id"];
                }

                $rows[$count]['id'] = $row['id'];
                if ($this->type == 'attachment' ) {
                    $bnames = $this->get_banned_names($row['id']);
                    foreach ($bnames as $bname) {
                       $rows[$count]['file'] .= $bname . "<br>";
                    }
                } elseif ($this->type == 'virus') {
                    $vnames=$this->get_virus_names($row['id']);
                    $rows[$count]['virus_name'] = "";
                    foreach ($vnames as $vname) {
                       $vurl = get_virus_info_url($vname);
                       if ($vurl == "") {
                          $rows[$count]['virus_name'] .= $row["virus_name"];
                       } else {
                          $rows[$count]['virus_name'] .= "<a href=\"" . $vurl . "\">" . $vname . "</a>";
                       }
                       $rows[$count]['virus_name'] .= "<br>";
                   }
                }
                $rows[$count]['received_date'] = $row["received_date"];
                $rows[$count]['sender_email'] = $magic_quotes ? stripslashes($row["sender_email"]) : $row["sender_email"];
                $rows[$count]['score'] = $row['score'];
 
                    $to_list = explode(" ", $row["envelope_to"]);
                    $rectmp = "";
                    foreach ($to_list as $recipient) {
                        if (isset($personal_addresses[$recipient]) || $domain_default) {
                          $rectmp[] = $recipient;
                        }
                    }
                    $rows[$count]['recipient_email'] = $rectmp;

           $subject = $magic_quotes ? stripslashes($row['subject']) : $row['subject'];
           if ($subject == "") {
              $subject = "(" . $lang['text_no_subject'] . ")";
           }else{
              if (preg_match('/=\?.+\?=/', $subject)) {
                 $subject = htmlspecialchars(iconv_mime_decode($subject, 2, 'utf-8'), ENT_NOQUOTES, 'UTF-8');
              } else {
                 $subject = htmlspecialchars($subject);
              }
           }
                  $rows[$count]['subject'] = $subject;
                  $count++;
          }

              $this->smarty->assign("row", $rows); 
              $this->smarty->assign("maxid", $maxid);

              $this->smarty->assign("links", $paged_data['links']);
        } else {
            $_SESSION["message"] = $message;
            header("Location: welcome.php" . $sid);
            exit;
        } 
        $this->smarty->assign("nothing_to_show", $nothing_to_show);   
        $this->smarty->display("list-cache.tpl");
    }

    function get_virus_names($mail_id) {
        static $vsth;
        if (!isset($vsth)) {
            $vsth = $this->dbh->prepare("SELECT virus_name FROM maia_viruses_detected LEFT JOIN maia_viruses " .
                                        "ON maia_viruses.id = maia_viruses_detected.virus_id " .
                                        "WHERE maia_viruses_detected.mail_id=?");
        }

        $result = $vsth->execute(array($mail_id));
        $ret = array();
        while ($row = $result->fetchrow()) {
            array_push($ret, $row['virus_name']);
        }
        return $ret;
    }

    function get_banned_names($mail_id) {
        static $bsth;
        if (!isset($bsth)) {
            $bsth = $this->dbh->prepare("SELECT file_name, file_type FROM maia_banned_attachments_found " .
                                        "WHERE mail_id=?");
        }

        $result = $bsth->execute(array($mail_id));
        $ret = array();
        while ($row = $result->fetchrow()) {
            array_push($ret, $row['file_name'] . " (" . $row['file_type'] . ")" );
        }
        return $ret;
    }

}
?>
