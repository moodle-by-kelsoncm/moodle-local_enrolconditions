Privacidade
===========

Nenhum dado sai do Moodle.

* O ``local_enrolconditions`` guarda, para cada inscrição suspensa ou registrada porque as condições deixaram de ser cumpridas, o método de inscrição, a inscrição, a ação, o motivo e as datas. Esses registros são exportados e excluídos com o curso ou com o usuário, pela Privacy API. As árvores de condições pertencem aos métodos de inscrição que as usam.
* O ``enrolcond_userfile`` encontra os usuários listados nos arquivos de usernames: a solicitação de dados desses usuários exporta os nomes dos arquivos, e a exclusão remove a linha deles dos arquivos.
* Os demais tipos de condição não guardam dados pessoais. As listas de consulta leem dados que já estão no Moodle; só administradores as definem (veja :doc:`administration`).
