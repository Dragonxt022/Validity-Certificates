<?php

/*
Plugin Name: Certidões
Description: Cadastre certidões com data devalidade.
Version: 1.2
Author: Pissinet Sites
*/

// Adicione uma página ao painel administrativo
function certidoes_menu_page() {
    add_menu_page(
        'Certidões',
        'Certidões',
        'manage_options',
        'certidoes-menu',
        'certidoes_admin_page'
    );
}
add_action('admin_menu', 'certidoes_menu_page');

// Adicione ação para verificar certidões vencidas
add_action('admin_init', 'verificar_certidoes_vencidas');

function verificar_certidoes_vencidas() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'certidoes_table';
    $certidoes = $wpdb->get_results("SELECT * FROM $table_name");

    foreach ($certidoes as $certidao) {
        if ($certidao->data_validade !== null) {
            $data_validade = strtotime($certidao->data_validade);
            $hoje = strtotime(date('Y-m-d'));

            // Verificar se a certidão está vencida
            if ($data_validade < $hoje) {
                // Certidão vencida, enviar e-mail de aviso
                enviar_email_aviso_certidao_vencida($certidao->nome_documento);
            }
        }
    }
}

function enviar_email_aviso_certidao_vencida($nome_documento) {
    // Configurar os detalhes do e-mail
    $to = 'pissinatti2019@gmail.com';
    $subject = 'Aviso: Certidão Vencida';
    $message = 'A certidão ' . $nome_documento . ' está vencida. Por favor, atualize.';

    // Enviar o e-mail
    wp_mail($to, $subject, $message);
}


// Função para exibir a página administrativa
function certidoes_admin_page() {
    global $wpdb;

    // Verificar certidões vencidas e enviar e-mails de aviso
    verificar_certidoes_vencidas();

    // Verificar se a tabela existe, senão, criá-la
    $table_name = $wpdb->prefix . 'certidoes_table';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nome_documento VARCHAR(255) NOT NULL,
            data_validade DATE,
            documento_path VARCHAR(255) NOT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    ?>
    <style>

        .FormularioCertidao {
            display: flex;
            flex-direction: column;
            padding: 5px;
        }

        .FormularioCertidao label {
            font-weight: 700;
            padding-bottom: 6px;
            
        }

        .wrap1{
            background-color: #f9fdff;
            padding: 15px;
            border-radius: 20px;
        }

    </style>
    <div class="wrap wrap1">
        <h2>Certidões</h2>
        <p>Mostrar somente a tabela [certidoes_tabela_usuario] <br>
        Mostrar tabela Administrativa [certidoes_tabela] </p>
        
        <!-- Formulário de adição de certidões -->
        <form class="FormularioCertidao" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="certidoes_save">
            <?php wp_nonce_field('certidoes_save_nonce', 'certidoes_save_nonce'); ?>

            <label for="nome_documento">Nome do Arquivo:</label>
            <input type="text" name="nome_documento" required>

            <label for="data_validade">Data de Validade:</label>
            <input type="date" name="data_validade">

            <label for="documento">Adicionar Documento:</label>
            <input type="file" name="documento" required>

            <?php submit_button('Salvar'); ?>
        </form>

        // Tabela de certidões cadastradas
        <?php

            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Documento Cadastrado</th><th>Data de Validade</th><th>Baixar</th></tr></thead>';
            echo '<tbody>';

            $table_name = $wpdb->prefix . 'certidoes_table';
            $certidoes = $wpdb->get_results("SELECT * FROM $table_name");

            foreach ($certidoes as $certidao) {
                echo '<tr>';

                // Nome do Documento
                echo '<td>' . esc_html($certidao->nome_documento) . '</td>';

                // Data de Validade ou Vitalício
                echo '<td';
                if ($certidao->data_validade !== null) {
                    $data_validade = strtotime($certidao->data_validade);
                    $hoje = strtotime(date('Y-m-d'));
                    $cor_destaque = ($data_validade < $hoje) ? ' style="color: red;"' : '';
                    echo $cor_destaque . '>' . date_i18n('d/m/Y', $data_validade);
                } else {
                    echo ' style="color: blue;">Vitalício';
                }
                echo '</td>';

                // Obtenha a URL do diretório de uploads usando wp_upload_dir()
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $certidao->documento_path);

                // Baixar Documento
                echo '<td><a href="' . esc_url($file_path) . '" target="_blank">Baixar</a> | <a href="' . admin_url('admin-post.php?action=certidoes_delete&id=' . $certidao->id) . '">Excluir</a></td>';
                    echo '</tr>';

                echo '</tr>';
            }

            echo '</tbody></table>';

        ?>
        <p>Pissintsites soluções <br> www.pissinetsites.com.br</p>
    </div>

    <?php
}

// Adicione ação para exclusão de documentos
function certidoes_delete_document() {
    if (isset($_GET['action']) && $_GET['action'] === 'certidoes_delete' && isset($_GET['id'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'certidoes_table';
        $document_id = absint($_GET['id']);

        // Obter informações do documento
        $document_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $document_id));

        if ($document_info) {
            // Excluir o arquivo físico
            $file_path = $document_info->documento_path;
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Excluir a entrada no banco de dados
            $wpdb->delete($table_name, array('id' => $document_id));
        }

        // Redirecionar de volta para a página administrativa
        wp_redirect(admin_url('admin.php?page=certidoes-menu'));
        exit();
    }
}
add_action('admin_init', 'certidoes_delete_document');


// Adicione shortcode para exibir a tabela em uma página
function certidoes_shortcode() {
    ob_start();
    certidoes_admin_page();
    return ob_get_clean();
}
add_shortcode('certidoes_tabela', 'certidoes_shortcode');


function certidoes_process_form() {
    if (isset($_POST['nome_documento']) && isset($_POST['data_validade']) && isset($_FILES['documento'])) {
        global $wpdb;

        $nome_documento = sanitize_text_field($_POST['nome_documento']);
        $data_validade = ! empty($_POST['data_validade']) ? sanitize_text_field($_POST['data_validade']) : null;

        // Manipular o upload do documento (você pode personalizar conforme necessário)
        // $upload_dir = wp_upload_dir();
        // $documento_path = $upload_dir['url'] . '/' . basename($_FILES['documento']['name']);
        // move_uploaded_file($_FILES['documento']['tmp_name'], $documento_path);
        
        $upload_dir = wp_upload_dir();
        $documento_path = $upload_dir['path'] . '/' . basename($_FILES['documento']['name']);
        move_uploaded_file($_FILES['documento']['tmp_name'], $documento_path);

        // Verificar se a tabela existe, senão, criá-la
        $table_name = $wpdb->prefix . 'certidoes_table';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                nome_documento VARCHAR(255) NOT NULL,
                data_validade DATE,
                documento_path VARCHAR(255) NOT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=InnoDB;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Salvar os dados no banco de dados
        $wpdb->insert($table_name, array('nome_documento' => $nome_documento, 'data_validade' => $data_validade, 'documento_path' => $documento_path));

        // Redirecionar de volta para a página do plugin
        wp_redirect(admin_url('admin.php?page=certidoes-menu'));
        exit();
    }
}
add_action('admin_post_certidoes_save', 'certidoes_process_form');


// Adicione shortcode para exibir apenas a tabela em uma página
function certidoes_tabela_usuario_shortcode() {
    ob_start();
    certidoes_tabela_usuario_content();
    return ob_get_clean();
}
add_shortcode('certidoes_tabela_usuario', 'certidoes_tabela_usuario_shortcode');


// Função para exibir apenas a tabela
function certidoes_tabela_usuario_content() {
    global $wpdb;

    // Tabela de certidões cadastradas
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Documento Cadastrado</th><th>Data de Validade</th><th>Baixar</th></tr></thead>';
    echo '<tbody>';

    $table_name = $wpdb->prefix . 'certidoes_table';
    $certidoes = $wpdb->get_results("SELECT * FROM $table_name");

    foreach ($certidoes as $certidao) {
        echo '<tr>';

        // Nome do Documento
        echo '<td>' . esc_html($certidao->nome_documento) . '</td>';

        // Data de Validade ou Vitalício
        echo '<td';
        if ($certidao->data_validade !== null) {
            $data_validade = strtotime($certidao->data_validade);
            $hoje = strtotime(date('Y-m-d'));
            $cor_destaque = ($data_validade < $hoje) ? ' style="color: red;"' : '';
            echo $cor_destaque . '>' . date_i18n('d/m/Y', $data_validade);
        } else {
            echo ' style="color: blue;">Vitalício';
        }
        echo '</td>';

        // Obtenha a URL do diretório de uploads usando wp_upload_dir()
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $certidao->documento_path);

        // Baixar Documento
        echo '<td><a href="' . esc_url($file_path) . '" target="_blank">Baixar</a></td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
}


