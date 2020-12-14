<?php

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/default.php');

global $DB, $OUTPUT, $PAGE;

$table = new html_table();
// Only print headers if not asked to download data
// Print the page header
$PAGE->set_title('Rapport personnalisé');
$PAGE->set_heading('Rapport personnalisé');



$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);
$q=$_GET['quizid'];

if ($id) {
    if (!$cm = get_coursemodule_from_id('quiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$quiz = $DB->get_record('quiz', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    if (!$quiz = $DB->get_record('quiz', array('id' => $q))) {
        print_error('invalidquizid', 'quiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

$url = new moodle_url('/mod/quiz/report_admin.php', array('quizid' => $q));
if ($mode !== '') {
    $url->param('mode', $mode);
}

$PAGE->set_url($url);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_pagelayout('report');

$reportlist = quiz_report_list($context);
if (empty($reportlist)) {
    print_error('erroraccessingreport', 'quiz');
}

echo $OUTPUT->header();
   
$sql ='select userid,lastname,firstname,username,timestart,timefinish,sumgrades,state
        from mdl_quiz_attempts qa 
        inner join mdl_user u on u.id=qa.userid  
        where qa.quiz=' . $_GET['quizid'] . ' order by lastname';
$rec=$DB->get_records_sql($sql);

$table->head = array('Prenom','Nom', 'Utilisateur','Etat','Heure début','Heure fin' , 'Note');
$cpt =1;
foreach ($rec as $records) {
    $sqlAttempts ='select qqa.id,q.name from mdl_quiz_attempts qa 
        inner JOIN mdl_question_usages qu ON qu.id = qa.uniqueid 
        inner JOIN mdl_question_attempts qqa ON qqa.questionusageid = qu.id
        inner join mdl_question q on q.id=qqa.questionid
        where qa.quiz=' . $_GET['quizid'].
        ' and qa.userid='.$records->userid;

    $attemps=$DB->get_records_sql($sqlAttempts);
    while ( $cpt <= count($attemps)) {
        $table->head[] = "Intitulé question. ".$cpt;
        $table->head[] = "Q.".$cpt  ;
        $cpt ++;
    }
    $firstname = $records->firstname;
    $lastname = $records->lastname;
    $username = $records->username;
    $state = $records->state;
    $timestart = $records->timestart;
    $timefinish = $records->timefinish;
    $sumgrades =  number_format((float)$records->sumgrades, 2, '.', '');
    $renderTab = array($firstname, $lastname,$username, $state,
                date('m/d/Y H:i:s',$timestart),
                date('m/d/Y H:i:s',$timefinish ), 
                $sumgrades
            );    
    foreach ($attemps as $key => $value) {
        if ($value->name == null ) 
            array_push($renderTab,'');
        else
            array_push($renderTab,$value->name);

        $sqlQuestion ='select fraction from mdl_question_attempt_steps qa 
            where fraction IS NOT NULL  and  questionattemptid='.$value->id .' order by timecreated desc limit 1';
        $resultQuestion=$DB->get_records_sql($sqlQuestion); 
        if ( $resultQuestion == null )  
            array_push($renderTab,'');
        else { 
            foreach ($resultQuestion as $keyQuestion => $valueQuestion) {
                array_push($renderTab,number_format((float)$keyQuestion, 2, '.', ''));
            }
        }
    }
    $table->data[]=$renderTab ;

}
echo html_writer::table($table);


echo $OUTPUT->footer();