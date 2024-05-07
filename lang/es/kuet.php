<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Spanish language strings
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Kuet';
$string['pluginadministration'] = 'Administración Kuet';
$string['modulename'] = 'Kuet';
$string['modulenameplural'] = 'Kuets';
$string['kuet:addinstance'] = 'Añadir un nuevo Kuet';
$string['kuet:view'] = 'Ver Kuet';
$string['kuet:managesessions'] = 'Gestionar Sesiones';
$string['kuet:startsession'] = 'Iniciar Sessiones';
$string['kuet:viewanonymousanswers'] = 'Ver respuestas anónimas';
$string['name'] = 'Nombre';
$string['introduction'] = 'Descripción';
$string['questiontime'] = 'Tiempo de Pregunta';
$string['questiontime_desc'] = 'Tiempo para cada pregunta en segundos.';
$string['questiontime_help'] = 'Tiempo para cada pregunta en segundos.';
$string['completiondetail:answerall'] = 'Participar en una sesión contestando preguntas';
$string['completionansweralllabel'] = 'Participar en una sesión';
$string['completionansweralldesc'] = 'Participar en una sesión contestando preguntas';
$string['configtitle'] = 'Kuet';
$string['generalsettings'] = 'Configuración General';
$string['socket'] = 'Socket';
$string['sockettype'] = 'Tipo de Socket';
$string['sockettype_desc'] = 'Para iniciar sesiones manuales, es necesario tener configuracion un socket. Puede ser local o externo: <ul><li><b>Sin socket: </b>Las sesiones manual no estarán disponibles</li><li><b>Socket local: </b>EL socket se iniciará en el mismo servidor que esta platafomra Moodle (es necesario tener certificados).</li><li><b>Externo: </b>Puedes iniciar el socket en un servidor externo, configurando dicha conexión con una url y un puerto.</li></ul>';
$string['nosocket'] = 'Sin uso de socket';
$string['local'] = 'Local';
$string['external'] = 'Externo';
$string['externalurl'] = 'URL externa';
$string['externalurl_desc'] = 'URL donde el socket está alojado. Puede ser una IP, pero debe tener protocolo HTTPS.';
$string['downloadsocket'] = 'Descargar el fichero para ejecutarlo en el servidor externo';
$string['downloadsocket_desc'] = 'Este fichero de descarga es necesario ejecutarlo en el servidor externo.<br>El administrador de la máquina (servidor externo) debe proporcionarle un puerto y los certificados.<br>Es responsabilidad del administrador asegurar que el socket funciona permanentemente.<br>';
$string['scriptphp'] = 'Descargar Archivo PHP';
$string['certificate'] = 'Certificado';
$string['certificate_desc'] = 'Fichero de certificado SSL válido para el servidor .crt o .pem. Es posible que este archivo ya esté generado en el servidor, o puedes crear archivos únicos para este mod usando herramientas como <a href="https://zerossl.com" target="_blank">zerossl.com</a>.';
$string['privatekey'] = 'Clave privada';
$string['privatekey_desc'] = 'Fichero con la clave privada para el servidor .pem or .key. Es posible que este archivo ya esté generado en el servidor, o puedes crear archivos únicos para este mod usando herramientas como <a href="https://zerossl.com" target="_blank">zerossl.com</a>.';
$string['testssl'] = 'Prueba de conexión';
$string['testssl_desc'] = 'Prueba de conexión con certificados SSL';
$string['validcertificates'] = 'Certificados y Puerto válidos';
$string['invalidcertificates'] = 'Certificados y Puerto inválidos';
$string['connectionclosed'] = 'Conexión cerrada';
$string['port'] = 'Puerto';
$string['port_desc'] = 'Puerto para realizar la conexión. Este puerto debe estar abierto, por lo que deberá consultar con el administrador del sistema..';
$string['warningtest'] = 'Esto intentará una conexión al socket con la configuración actual. <b>Guarde la configuración antes de realizar la prueba.</b>';
$string['session_name'] = 'Nombre de la Sesion';
$string['session_name_placeholder'] = 'Nombre de la Sesion';
$string['session_name_help'] = 'Escribe un nombre de sesión';
$string['anonymousanswer'] = 'Respuestas anónimas';
$string['anonymousanswer_help'] = 'Elige una opción.';
$string['countdown'] = 'Mostrar cuenta atrás';
$string['randomquestions'] = 'Preguntas aleatorias';
$string['randomanswers'] = 'Respuestas aleatorias';
$string['showfeedback'] = 'Mostrar retroalimentación';
$string['showfinalgrade'] = 'Mostrar nota final';
$string['timesettings'] = 'Configuración de Tiempos';
$string['startdate'] = 'Fecha de inicio de sesión';
$string['enddate'] = 'Fecha fin de sesión';
$string['automaticstart'] = 'Inicio automático';
$string['timelimit'] = 'Tiempo límite';
$string['accessrestrictions'] = 'Restricciones de acceso';
$string['next'] = 'Siguiente';
$string['sessions'] = 'Sesiones';
$string['sessions_info'] = 'Todas las sesiones son mostradas';
$string['reports'] = 'Informes';
$string['reports_info'] = 'Todas las sesiones completadas se muestran para poder acceder a los informes.';
$string['sessionreport'] = 'Informe de la sesión';
$string['sessionreport_info'] = 'Se muestra el informe de la sesión.';
$string['report'] = 'Informe';
$string['active_sessions'] = 'Sesiones activas';
$string['completed_sessions'] = 'Sesiones completadas';
$string['create_session'] = 'Crear sesión';
$string['questions_number'] = 'Nº de preguntas';
$string['question_number'] = 'Nº de la pregunta';
$string['session_date'] = 'Fecha';
$string['session_finishingdate'] = 'Fecha fin';
$string['session_actions'] = 'Acciones';
$string['init_session'] = 'Iniciar sesión';
$string['init_session_desc'] = 'Si inicia una sesión manualmente, puede bloquear las sesiones programadas con inicio automático. Asegúrese de que no haya sesiones próximas antes de comenzar esta sesión.<br>¿Seguro que quieres iniciar la sesión? ';
$string['end_session'] = 'Finalizar sesión';
$string['end_session_error'] = 'La sesión no puede finalizar debido a un error de comunicación con el servidor, por favor, inténtelo más tarde.';
$string['end_session_desc'] = '¿Seguro que quieres finalizar la sesión?';
$string['end_session_manual_desc'] = 'Si finalizas la sesión, cerrarás la conexión de todos los estudiantes y ya no podrán responder este cuestionario.<br><b>¿Seguro que quieres finalizar la sesión?</b>';
$string['viewreport_session'] = 'Ver informe';
$string['edit_session'] = 'Editar sesión';
$string['copy_session'] = 'Copiar sesión';
$string['delete_session'] = 'Eliminar sesión';
$string['copysession'] = 'Copiar sesión';
$string['copysession_desc'] = '¿Seguro que quieres copiar la sesión? Si la sesión tiene fechas de inicio automático o de inicio y finalización, será necesario restablecerlas.';
$string['copysessionerror'] = 'Ha ocurrido un error copiando la sesión. Comprueba que tienes la capacidad "mod/kuet:managesessions", o inténtalo más tarde.';
$string['deletesession'] = 'Eliminar sesión';
$string['deletesession_desc'] = '¿Seguro que quieres eliminar la sesión?';
$string['deletesessionerror'] = 'Ha ocurrido un error eliminando la sesión. Comprueba que tienes la capacidad "mod/kuet:managesessions", o inténtalo más tarde.';
$string['confirm'] = 'Aceptar';
$string['copy'] = 'Copiar';
$string['groupings'] = 'Agrupaciones';
$string['anonymiseresponses'] = 'Anonimizar las respuestas de los estudiantes';
$string['noanonymiseresponses'] = 'No anonimizar las respuestas de los estudiantes';
$string['sessionconfiguration'] = 'Configuración de la sesión';
$string['sessionconfiguration_info'] = 'Configura tu propia sesión';
$string['questionsconfiguration'] = 'Configuración de la pregunta';
$string['questionsconfiguration_info'] = 'Añade preguntas a la sesión';
$string['summarysession'] = 'Resumen de la sesión';
$string['summarysession_info'] = 'Resumen de la sesión';
$string['sessionstarted'] = 'Sesión iniciada';
$string['multiplesessionerror'] = 'Esta sesión no está activa o ya no existe.';
$string['notactivesession'] = 'Oops, parece que tu profesor no ha iniciado todavía la sesión...';
$string['notactivesessionawait'] = 'Espera a que lo inicie o consulta tus últimos informes.';
$string['nextsession'] = 'Próxima sesión:';
$string['nosession'] = 'No hay sesiones iniciadas por el profesor';
$string['questions_list'] = 'Preguntas selecionadas';
$string['questions_bank'] = 'Banco de preguntas';
$string['question_position'] = 'Posición';
$string['question_name'] = 'Nombre';
$string['question_type'] = 'Tipo';
$string['question_version'] = 'Versión';
$string['question_isvalid'] = 'Es válida';
$string['question_actions'] = 'Acciones';
$string['improvise_cloudtags'] = 'Improvisa Nube de tags';
$string['select_category'] = 'Selecciona una categoría';
$string['go_questionbank'] = 'Ir al banco de preguntas';
$string['selectall'] = 'Seleccionar/Desseleccionar todo';
$string['selectvisibles'] = 'Seleccionar/Desseleccionar visibles';
$string['add_questions'] = 'Añadir preguntas';
$string['number_select'] = 'Preguntas seleccionadas: ';
$string['changecategory'] = 'Cambiar categoría';
$string['changecategory_desc'] = 'Ha seleccionado preguntas que no se han agregado a la sesión. Si cambias de categoría perderás esta selección. ¿Desea continuar?';
$string['selectone'] = 'Seleccionar preguntas';
$string['selectone_desc'] = 'Selecciona al menos una pregunta para añadir a la sesión.';
$string['addquestions'] = 'Añadir preguntas';
$string['addquestions_desc'] = '¿Estás seguro que quieres aádir {$a} preguntas a la sesión?';
$string['deletequestion'] = 'Eliminar pregunta de la sesión';
$string['deletequestion_desc'] = '¿Estás seguro que quieres eliminar esta pregunta de la sesión?';
$string['gradesheader'] = 'Calificacón de la pregunta';
$string['nograding'] = 'Ignorar respuesta correcta y calificación';
$string['sessionalreadyexists'] = 'El nombre de sesión ya existe';
$string['showgraderanking'] = 'Mostrar clasificación entre preguntas';
$string['question_nosuitable'] = 'No compatible con Kuet.';
$string['configuration'] = 'Configuración';
$string['end'] = 'Fin';
$string['questionidnotsent'] = 'Id de pregunta no enviado';
$string['question_index_string'] = '{$a->num} de {$a->total}';
$string['question'] = 'Pregunta';
$string['feedback'] = 'Retroalimentación';
$string['session_info'] = 'Información de la sesión';
$string['results'] = 'Resultados';
$string['students'] = 'Alumnos';
$string['corrects'] = 'Correctas';
$string['incorrects'] = 'Incorrectas';
$string['notanswers'] = 'Sin contestar';
$string['points'] = 'Puntos';
$string['inactive_manual'] = 'Manual inactivo';
$string['inactive_programmed'] = 'Programado inactivo';
$string['podium_manual'] = 'Manual podio';
$string['podium_programmed'] = 'Programado Podio';
$string['race_manual'] = 'Manual Carrera';
$string['race_programmed'] = 'Programado Carrera';
$string['sessionmode'] = 'Modo de sesión';
$string['sessionmode_help'] = 'Los modos de sesión son distintas formas de mostrar las sesiones.';
$string['countdown_help'] = 'Habilita esta opción para que los estudiantes vean la cuenta atrás en cada pregunta. (Sólo si la pregunta dispone de tiempo)';
$string['showgraderanking_help'] = 'El profesor no verá la clasificación durante una sesión en vivo. Sólo disponible en los modos de sesión de podios.';
$string['showgraderankinghelp'] = 'El profesor no verá la clasificación durante una sesión en vivo. Sólo disponible en los modos de sesión de podios..';
$string['randomquestions_help'] = 'Las preguntas aparecerán en orden aleatorio para cada estudiante. Sólo disponible es sesiones programadas.';
$string['randomanswers_help'] = 'Las respuestas aparecerán en orden aleatorio para cada estudiante..';
$string['showfeedback_help'] = 'Después de responder cada pregunta, aparecerán comentarios. En el modo manual, el profesor puede mostrar u ocultar los comentarios de cada pregunta (solo si la pregunta contiene comentarios).';
$string['showfinalgrade_help'] = 'La nota final aparecerá al finalizar la sesión.';
$string['startdate_help'] = 'La sesión comenzará automáticamente en esta fecha. La fecha de inicio solo estará disponible con sesiones programadas.';
$string['enddate_help'] = 'La sesión finalizará automáticamente en esta fecha. La fecha de finalización solo estará disponible con sesiones programadas.';
$string['automaticstart_help'] = 'La sesión comenzará y finalizará automáticamente si se establecen fechas para la misma, por lo que no es necesario iniciarla manualmente.';
$string['timelimit_help'] = 'Tiempo total para la sesión';
$string['waitingroom'] = 'Sala de espera';
$string['waitingroom_info'] = 'Comprueba que todo está correcto antes de empezar la sesión.';
$string['sessionstarted_info'] = 'Has iniciado la sesión, ahora puedes hacer seguimiento de las preguntas.';
$string['participants'] = 'Participantes';
$string['waitingroom_message'] = 'Espera, en seguida comenzamos....';
$string['ready_users'] = 'Participantes preparados';
$string['ready_groups'] = 'Grupos preparados';
$string['session_closed'] = 'La conexión se ha cerrado.';
$string['session_closed_info'] = 'Esto puede ser debido a la finalización de la sesión o por problemas técnicos con la conexión. Por favor, vuelve a la sesión para reconectar o ponte en contacto con tu profesor.';
$string['system_error'] = 'Ha ocurrido un error y la conexión se ha cerrado.<br>No es posible continuar con esta sesión.';
$string['connection_closed'] = 'Conexión Cerrada {$a->reason} - {$a->code}';
$string['backtopanelfromsession'] = 'Volver al panel de sesiones';
$string['backtopanelfromsession_desc'] = 'Si vuelves, la sesión no será iniciada pero la podrás iniciar cuando quieras. ¿Quieres volver al panel de sesiones?';
$string['lowspeed'] = 'Your internet connection seems slow or unstable ({$a->downlink} Mbps, {$a->effectiveType}). This may cause unexpected behaviour, or sudden closure of the session.<br>We recommend that you do not init session until you have a good internet connection.';
$string['alreadyteacher'] = 'Ya hay un profesor dirigiendo esta sesión, no puedes conectarte. Por favor, espera a que termine esta sesión para poder entrar.';
$string['userdisconnected'] = 'El usuario {$a} se ha desconectado.';
$string['qtimelimit_help'] = 'Tiempo para contestar la respuesta. Útil cuando el tiempo de la sesión es la suma de los tiempos de las preguntas.';
$string['sessionlimittimebyquestionsenabled'] = 'Esta sesión tiene un tiempo limitado de {$a}. El tiempo total de cada pregunta se calculará dividiendo el tiempo total de la sesión por el número de preguntas.<br>Si quieres añadir un tiempo por pregunta, debes configurar el modo de sesión como "Tiempo por pregunta", indicar un valor por defecto y a continuación podrás modificar el tiempo de cada pregunta.';
$string['notimelimitenabled'] = 'La sesión se fija sin límite de tiempo..<br>Si desea agregar un tiempo por pregunta, debe especificar el modo de sesión en "Tiempo por pregunta", especificar un tiempo predeterminado y luego puede establecer un tiempo para cada pregunta usando este formulario.';
$string['incompatible_question'] = 'Pregunta no compatible';
$string['controlpanel'] = 'Panel de control';
$string['control'] = 'Control';
$string['pause'] = 'Pausa';
$string['play'] = 'Play';
$string['resend'] = 'Reenviar';
$string['jump'] = 'Saltar';
$string['finishquestion'] = 'Terminar pregunta';
$string['showhide'] = 'Mostrar / Ocultar';
$string['responses'] = 'Respuestas';
$string['statistics'] = 'Estadísticas';
$string['questions'] = 'Preguntas';
$string['improvise'] = 'Improvisa';
$string['vote'] = 'Vota';
$string['vote_tags'] = 'Vota tags';
$string['incorrect_sessionmode'] = 'Modo de sesión no válida';
$string['endsession'] = 'Session terminada';
$string['endsession_info'] = 'Has alcanzado el final de la sesión, ahora puedes ver el informe con tus resultados o continuar con el curso.';
$string['timemode'] = 'Modo tiempo';
$string['no_time'] = 'Sin tiempo';
$string['session_time'] = 'Tiempo total de sesión';
$string['session_time_resume'] = 'Tiempo total de sesión: {$a}';
$string['sessiontime'] = 'Tiempo de Sesión';
$string['timeperquestion'] = 'Tiempo por pregunta';
$string['sessiontime_help'] = 'El tiempo establecido se dividirá entre el número de preguntas, y se asignará el mismo tiempo a todas las preguntas.';
$string['question_time'] = 'Tiempo por pregunta';
$string['question_time_help'] = 'Se configura un tiempo por pregunta (Puedes hacerlo tras añadir una pregunta). A default time will be set to allocate to those questions that do not have a defined time.';
$string['timemode_help'] = 'Hay que tener en cuenta que el tiempo por pregunta corresponde al tiempo permitido para responder. Una vez que se responda, se parará el tiempo <br><br><b> Sin tiempo: </b> No hay limite de tiempo para finalizar la sesión. El tiempo por pregunta se puede configurar en algunas o ninguna desde el panel de preguntas <br><b>Tiempo total por sesión:</b> Cada pregunta tendrá el mismo tiempo para responder (este valor se dividirá entre el número de preguntas) <br><b>Tiempo pro pregunta:</b> Este valor será el de por defecto de cada pregunta. (En cada pregunta se podrá modificar dicho valor) <br><br><b>Importante:</b> Si durante una pregunta con tiempo definido, el alumno cierra el navegador o refresca la página, esa pregunta será considerada no enviada (por considerarse un intento de ganar tiempo para contestar).';
$string['erroreditsessionactive'] = 'No es posible editar una sesión activa';
$string['activesessionmanagement'] = 'Gestión de sesiones activas';
$string['sessionnoquestions'] = 'No se han añadido preguntas a la sesión';
$string['sessioncreating'] = 'No has terminado todavía de editar la sesión. Debes alcanzar el paso 3 y pulsar en el botón de Terminar';
$string['sessionconflict'] = 'Esta sesión tiene un conflicto de fechas con otras sesiones y no comenzará automáticamente hasta que se resuelva el conflicto.';
$string['sessionwarning'] = 'Esta sesión debería haber empezado, pero hay una sesión activa que lo impide. En cuanto acabe dicha sesión, comenzará esta automáticamente.';
$string['sessionerror'] = 'Ha ocurrido un error en esta sesión y no se puede continuar (borrado de grupos o agrupamientos activos, borrado de preguntas, modificación de la configuración, etc.). Puedes borrarla y/o duplicarla (Asegúratede que la configuración es correcta)';
$string['startminorend'] = 'La fecha fin de la sesión no puede ser igual o anterior a la fecha de inicio.';
$string['previousstarterror'] = 'La fecha de inicio de la sesión no puede ser posterior a la fecha fin.';
$string['sessionmanualactivated'] = 'La sesión {$a->sessionid} está activa en kuetid -> {$a->kuetid}. El resto de la sesión se omite hasta el final de esta sesión.';
$string['sessionactivated'] = 'Sesión {$a->sessionid} activada para kuetid {$a->kuetid}';
$string['sessionfinished'] = 'Sesión {$a->sessionid} finalizada para kuetid {$a->kuetid}';
$string['sessionfinishedformoreone'] = 'Sesión {$a->sessionid} finalizada para kuetid {$a->kuetid} debido a que ya hay una sesión activa.';
$string['error_initsession'] = 'Error de inicio de sesión';
$string['error_initsession_desc'] = 'La sesión no puede iniciarse, puede que haya una sesión ya iniciada o debido a un error. Por favor, recarga la página.';
$string['success'] = 'Correcta';
$string['noresponse'] = 'Sin responder';
$string['noevaluable'] = 'No evaluable';
$string['invalid'] = 'Incorrecta';
$string['ranking'] = 'Clasificación';
$string['participant'] = 'Participante';
$string['score'] = 'Puntuación';
$string['viewreport_user'] = 'Informe de usuario';
$string['viewreport_group'] = 'Informe de grupo';
$string['otheruserreport'] = 'No puedes ver el informe de otro alumno';
$string['userreport'] = 'Informe de sesión de usuario';
$string['userreport_info'] = 'Se muestran los resultados de la sesión del alumno.';
$string['groupreport'] = 'Informe de sesión de grupo';
$string['groupreport_info'] = 'Se muestran los resultados de la sesión del grupo.';
$string['viewquestion_user'] = 'Ver respuesta';
$string['questionreport'] = 'Informe de pregunta';
$string['questionreport_info'] = 'Se muestra el informe de la pregunta.';
$string['preview'] = 'Previsualización';
$string['percent_correct'] = '% correctas';
$string['percent_incorrect'] = '% incorrectas';
$string['percent_partially'] = '% Parcialmente correctas';
$string['percent_noresponse'] = '% Sin responder';
$string['student_number'] = 'Nº de alumnos';
$string['correct'] = 'Correcta';
$string['incorrect'] = 'Incorrecta';
$string['response'] = 'Respuesta';
$string['score_moment'] = 'Calificación de la pregunta';
$string['time'] = 'Tiempo';
$string['status'] = 'Estado';
$string['anonymousanswers'] = 'Las respuestas en este cuestionario son anónimas.';
$string['kuetnotexist'] = 'Imposible encontrar el kuet con id {$a}';
$string['jumpto_error'] = 'Debe ser un número entre 1 y {$a}';
$string['session'] = 'Sesión';
$string['send_response'] = 'Enviar respuestas';
$string['partially_correct'] = 'Parcialmente correcta';
$string['partially'] = 'Parcialmente';
$string['scored_answers'] = 'Puntuación de las respuestas';
$string['provisional_ranking'] = 'Clasificación provisional';
$string['final_ranking'] = 'Clasificación Final';
$string['score_obtained'] = 'Puntuación obtenida';
$string['total_score'] = 'Puntuación Total';
$string['grademethod'] = 'Método de calificación';
$string['grademethod_help'] = 'Elige el modo para calificar este módulo de actividad. La nota aparecerá en el libro de calificaciones del curso.';
$string['nograde'] = 'Sin calificar';
$string['gradehighest'] = 'Sesión con la calificación más alta';
$string['gradeaverage'] = 'Media aritmética de las califiaciones de las sesiones';
$string['firstsession'] = 'Primera sesión calificable';
$string['lastsession'] = 'Última sesión calificable';
$string['sessionended'] = 'Sesión finalizada';
$string['sessionended_desc'] = 'Cuando una sesión finaliza, se lanza un evento para calcular la nota de la sesión del alumno.';
$string['sgrade'] = 'Calificar la sesión';
$string['sgrade_desc'] = 'Si está habilitado, la nota obtenida se mostrará en el libro de calificaciones.';
$string['sgrade_help'] = 'Chequea esta opción si quieres que la nota obtenida en la sesión sea parte de la nota de la actividad en el libro de calificaciones del curso.';
$string['cachedef_grades'] = 'This is the description of the kuet cache grades';
$string['qstatus_0'] = 'Incorrecta';
$string['qstatus_1'] = 'Correcta';
$string['qstatus_2'] = 'Parcialmente correcta';
$string['qstatus_3'] = 'Sin responder';
$string['qstatus_4'] = 'No evaluable';
$string['qstatus_5'] = 'Inválida';
$string['error_delete_instance'] = 'Error eliminando una instancia de kuet.';
$string['session_groupings_error'] = 'Esta actividad está configurada en modo grupos. Cada sesión debe tener seleccionado un agrupamiento.';
$string['session_groupings_no_members'] = 'El agrupamiento está vacio. Por favor, selecciona una agrupamiento con participantes.';
$string['session_groupings_same_user_in_groups'] = 'Cada participante, solo debe pertenecer a un grupo del agrupamiento. Comprueba los siguientes participantes: {$a}';
$string['groupmode'] = 'Modo equipos';
$string['fakegroup'] = 'Equipo Kuet {$a}';
$string['fakegroupdescription'] = 'La actividad Kuet ha creado este grupo porque hay participantes en este curso que no
pertenecen a ningun grupo selecionado.';
$string['groups'] = 'Equipos';
$string['abbreviationquestion'] = 'Q';
$string['timemodemustbeset'] = 'Se debe seleccionar un tiempo de sesión o de pregunta.';
$string['timecannotbezero'] = 'El tiempo no puede ser 0';
$string['nogroupingscreated'] = 'Esta actividad está en modo grupos pero no hay agrupamientos creados en este curso.
Es necesario que crees un agrupamiento para poderlo elegir en la actividad.';
$string['notallowedspecialchars'] = 'No se permiten caracteres especiales: ?!<>\\';
$string['units'] = 'Unidades';
$string['unit'] = 'Unidad';
$string['statement_improvising'] = 'Improvisa pregunta Nube de Tags';
$string['waitteacher'] = 'Esperando al profesor';
$string['teacherimprovising'] = 'El profesor está impovisando una pregunta "Nube de Tags", en la cual debes responder con una palabra.<br>En cuanto el profesor termine, la pregunta aparecerá en la pantalla, podrás responderla y ver las respuestas de tus compañeros.';
$string['statement_improvise'] = 'Statement of the cloud of tags';
$string['statement_improvise_help'] = 'Recuerda que debe ser una pregunta que se pueda responder preferiblemente con una palabra.';
$string['reply_improvise'] = 'Respuesta';
$string['reply_improvise_help'] = 'Sé el primero en añadir una palabra a la nube de tags. (Opcional)';
$string['reply_improvise_student_help'] = 'Intenta responder a la pregunta con una palabra.';
$string['submit'] = 'Enviar';
$string['sessionrankingreport'] = 'Informe de clasificación de la sesión';
$string['groupsessionrankingreport'] = 'Informe de clasificación de la sesión grupal';
$string['sessionquestionsreport'] = 'Informe de preguntas de sesión';
$string['reportlink'] = 'Enlace al informe';
$string['questionid'] = 'Id';
$string['isevaluable'] = '¿Es evaluable?';
$string['alreadyanswered'] = 'Un miembro del grupo ya ha contestado!';
$string['groupdisconnected'] = 'El grupo {$a} se ha desconectado.';
$string['groupmemberdisconnected'] = 'Este miembro del grupo {$a} se ha desconectado.';
$string['groupingremoved'] = 'Esta agrupamiento se ha eliminado o no tiene participantes. No puedes continuar con esta sesión.';
$string['groupremoved'] = 'Tu grupo ha sido eliminado o no pertenece al agrupamiento de la actividad. No puedes seguir con la sesión.';
$string['gocourse'] = 'Volver al curso';
$string['sessionerror'] = 'Esta sesión no está configurada correctamente';
$string['httpsrequired'] = 'Es obligatorio usar protocola HTTPS para poder usar Kuet.';
$string['sessionsnum'] = 'Number of sessions';
