<?
/**
 * UploadAccountHistoryAction
 */
class UploadAccountHistoryAction extends Action {


   /**
    * Führt die Action aus.
    *
    * @return ActionForward
    */
   public function execute(Request $request, Response $response) {
      if ($request->isPost())
         return $this->onPost($request, $response);

      return 'error';
   }


   /**
    * Verarbeitet einen POST-Request.
    *
    * @return ActionForward
    */
   public function onPost(Request $request, Response $response) {
      $form = $this->form;

      if ($form->validate()) {
         //set_time_limit(0);
         try {
            //EncashmentHelper ::updateEncashmentKeys($form->getFileTmpName());
            echo(HttpResponse ::SC_OK) ;
            return null;
         }
         catch (Exception $ex) {
            Logger ::log('System not available', $ex, L_ERROR, __CLASS__);
            $request->setActionError('', '503: server error, try again later');
         }
      }

      echo($request->getActionError()."\n") ;
      return null;
   }
}
?>