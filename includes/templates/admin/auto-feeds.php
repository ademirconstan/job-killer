<?php
/**
 * Admin Auto Feeds Template
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Feeds Automáticos', 'job-killer'); ?></h1>
    <p class="description"><?php _e('Configure feeds automáticos com provedores integrados. Não é necessário inserir URLs manualmente.', 'job-killer'); ?></p>
    
    <div class="job-killer-auto-feeds-header">
        <button type="button" class="button button-primary" id="add-auto-feed-btn">
            <?php _e('Adicionar Novo Feed', 'job-killer'); ?>
        </button>
    </div>

    <!-- Auto Feeds List -->
    <div class="job-killer-auto-feeds-list">
        <?php if (!empty($auto_feeds)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Nome', 'job-killer'); ?></th>
                    <th scope="col"><?php _e('Provedor', 'job-killer'); ?></th>
                    <th scope="col"><?php _e('Parâmetros', 'job-killer'); ?></th>
                    <th scope="col"><?php _e('Status', 'job-killer'); ?></th>
                    <th scope="col"><?php _e('Última Importação', 'job-killer'); ?></th>
                    <th scope="col"><?php _e('Ações', 'job-killer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auto_feeds as $feed_id => $feed): ?>
                <tr data-feed-id="<?php echo esc_attr($feed_id); ?>">
                    <td>
                        <strong><?php echo esc_html($feed['name']); ?></strong>
                    </td>
                    <td>
                        <?php
                        $provider_info = $this->providers_manager->get_provider_info($feed['provider_id']);
                        echo esc_html($provider_info['name'] ?? $feed['provider_id']);
                        ?>
                    </td>
                    <td>
                        <div class="feed-parameters">
                            <?php if (!empty($feed['parameters'])): ?>
                                <?php foreach ($feed['parameters'] as $key => $value): ?>
                                    <?php if (!empty($value)): ?>
                                    <span class="parameter-item">
                                        <strong><?php echo esc_html(ucfirst($key)); ?>:</strong> 
                                        <?php echo esc_html($value); ?>
                                    </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <em><?php _e('Nenhum parâmetro configurado', 'job-killer'); ?></em>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="feed-status <?php echo !empty($feed['active']) ? 'active' : 'inactive'; ?>">
                            <?php echo !empty($feed['active']) ? __('Ativo', 'job-killer') : __('Inativo', 'job-killer'); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        if (!empty($feed['last_import'])) {
                            $helper = new Job_Killer_Helper();
                            echo $helper->time_ago($feed['last_import']);
                        } else {
                            echo '<em>' . __('Nunca', 'job-killer') . '</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <span class="test">
                                <button type="button" class="button button-small test-auto-feed" data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                    <?php _e('Testar', 'job-killer'); ?>
                                </button>
                            </span>
                            
                            <span class="import">
                                <button type="button" class="button button-small import-auto-feed" data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                    <?php _e('Importar', 'job-killer'); ?>
                                </button>
                            </span>
                            
                            <span class="toggle">
                                <button type="button" class="button button-small toggle-auto-feed" data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                    <?php echo !empty($feed['active']) ? __('Desativar', 'job-killer') : __('Ativar', 'job-killer'); ?>
                                </button>
                            </span>
                            
                            <span class="edit">
                                <button type="button" class="button button-small edit-auto-feed" data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                    <?php _e('Editar', 'job-killer'); ?>
                                </button>
                            </span>
                            
                            <span class="delete">
                                <button type="button" class="button button-small button-link-delete delete-auto-feed" data-feed-id="<?php echo esc_attr($feed_id); ?>">
                                    <?php _e('Excluir', 'job-killer'); ?>
                                </button>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="notice notice-info">
            <p><?php _e('Nenhum feed automático configurado ainda.', 'job-killer'); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Auto Feed Modal -->
<div id="auto-feed-modal" class="job-killer-modal" style="display: none;">
    <div class="job-killer-modal-content">
        <div class="job-killer-modal-header">
            <h2 id="modal-title"><?php _e('Adicionar Feed Automático', 'job-killer'); ?></h2>
            <button type="button" class="job-killer-modal-close">&times;</button>
        </div>
        
        <div class="job-killer-modal-body">
            <form id="auto-feed-form">
                <input type="hidden" id="feed-id" name="feed[id]">
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="feed-name"><?php _e('Nome do Feed', 'job-killer'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="feed-name" name="feed[name]" class="regular-text" required>
                                <p class="description"><?php _e('Nome descritivo para identificar este feed', 'job-killer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="feed-provider"><?php _e('Provedor', 'job-killer'); ?></label>
                            </th>
                            <td>
                                <select id="feed-provider" name="feed[provider_id]" class="regular-text" required>
                                    <option value=""><?php _e('Selecione um provedor', 'job-killer'); ?></option>
                                    <?php foreach ($providers as $provider_id => $provider_info): ?>
                                    <option value="<?php echo esc_attr($provider_id); ?>">
                                        <?php echo esc_html($provider_info['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Selecione o provedor de vagas', 'job-killer'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Provider-specific fields will be loaded here -->
                <div id="provider-fields"></div>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Status', 'job-killer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="feed-active" name="feed[active]" value="1" checked>
                                    <?php _e('Ativar este feed imediatamente', 'job-killer'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
        
        <div class="job-killer-modal-footer">
            <button type="button" class="button button-secondary test-provider-btn">
                <?php _e('Testar Conexão', 'job-killer'); ?>
            </button>
            <button type="button" class="button button-primary save-auto-feed-btn">
                <?php _e('Salvar Feed', 'job-killer'); ?>
            </button>
            <button type="button" class="button button-secondary job-killer-modal-close">
                <?php _e('Cancelar', 'job-killer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Test Results -->
<div id="test-results" class="notice" style="display: none; margin-top: 20px;">
    <div id="test-results-content"></div>
</div>

<style>
.job-killer-auto-feeds-header {
    margin-bottom: 20px;
}

.feed-parameters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    max-width: 300px;
}

.parameter-item {
    background: #f6f7f7;
    color: #2c3338;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    margin: 0;
    border: 1px solid #dcdcde;
    line-height: 1.3;
    white-space: nowrap;
}

.feed-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.feed-status.active {
    background: #d1e7dd;
    color: #0f5132;
}

.feed-status.inactive {
    background: #f8d7da;
    color: #721c24;
}

.job-killer-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.job-killer-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 4px;
    width: 90%;
    max-width: 700px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.job-killer-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f9f9f9;
}

.job-killer-modal-header h2 {
    margin: 0;
    font-size: 18px;
}

.job-killer-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.job-killer-modal-close:hover {
    color: #d63638;
}

.job-killer-modal-body {
    padding: 20px;
}

.job-killer-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    background: #f9f9f9;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.provider-auth-fields,
.provider-parameter-fields {
    margin-top: 20px;
}

.provider-auth-fields h3,
.provider-parameter-fields h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
    color: #23282d;
}

@media (max-width: 768px) {
    .job-killer-modal-content {
        width: 95%;
        margin: 2% auto;
    }
    
    .feed-parameters {
        flex-direction: column;
    }
    
    .job-killer-modal-footer {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentFeedId = null;
    var providers = <?php echo wp_json_encode($providers); ?>;
    var autoFeeds = <?php echo wp_json_encode($auto_feeds); ?>;
    
    // Add new feed
    $('#add-auto-feed-btn').on('click', function() {
        currentFeedId = null;
        $('#modal-title').text('<?php _e('Adicionar Feed Automático', 'job-killer'); ?>');
        $('#auto-feed-form')[0].reset();
        $('#provider-fields').empty();
        $('.save-auto-feed-btn').text('<?php _e('Salvar Feed', 'job-killer'); ?>');
        $('#auto-feed-modal').show();
    });
    
    // Edit feed
    $('.edit-auto-feed').on('click', function() {
        var feedId = $(this).data('feed-id');
        var feed = autoFeeds[feedId];
        
        if (!feed) return;
        
        currentFeedId = feedId;
        $('#modal-title').text('<?php _e('Editar Feed Automático', 'job-killer'); ?>');
        $('#feed-id').val(feedId);
        $('#feed-name').val(feed.name);
        $('#feed-provider').val(feed.provider_id);
        $('#feed-active').prop('checked', feed.active);
        $('.save-auto-feed-btn').text('<?php _e('Atualizar Feed', 'job-killer'); ?>');
        
        // Load provider fields
        loadProviderFields(feed.provider_id, feed);
        
        $('#auto-feed-modal').show();
    });
    
    // Provider change
    $('#feed-provider').on('change', function() {
        var providerId = $(this).val();
        loadProviderFields(providerId);
    });
    
    // Load provider-specific fields
    function loadProviderFields(providerId, existingData) {
        if (!providerId || !providers[providerId]) {
            $('#provider-fields').empty();
            return;
        }
        
        var provider = providers[providerId];
        var html = '';
        
        // Auth fields
        if (provider.requires_auth && provider.auth_fields) {
            html += '<div class="provider-auth-fields">';
            html += '<h3><?php _e('Autenticação', 'job-killer'); ?></h3>';
            html += '<table class="form-table"><tbody>';
            
            Object.keys(provider.auth_fields).forEach(function(fieldKey) {
                var field = provider.auth_fields[fieldKey];
                var value = existingData && existingData.auth ? (existingData.auth[fieldKey] || '') : '';
                
                html += '<tr>';
                html += '<th scope="row"><label for="auth-' + fieldKey + '">' + field.label + '</label></th>';
                html += '<td>';
                html += '<input type="' + field.type + '" id="auth-' + fieldKey + '" name="feed[auth][' + fieldKey + ']" class="regular-text" value="' + value + '"' + (field.required ? ' required' : '') + '>';
                if (field.description) {
                    html += '<p class="description">' + field.description + '</p>';
                }
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            html += '</div>';
        }
        
        // Parameter fields
        if (provider.parameters) {
            html += '<div class="provider-parameter-fields">';
            html += '<h3><?php _e('Parâmetros de Busca', 'job-killer'); ?></h3>';
            html += '<table class="form-table"><tbody>';
            
            Object.keys(provider.parameters).forEach(function(fieldKey) {
                var field = provider.parameters[fieldKey];
                var value = existingData && existingData.parameters ? (existingData.parameters[fieldKey] || field.default || '') : (field.default || '');
                
                html += '<tr>';
                html += '<th scope="row"><label for="param-' + fieldKey + '">' + field.label + '</label></th>';
                html += '<td>';
                
                if (field.type === 'number') {
                    html += '<input type="number" id="param-' + fieldKey + '" name="feed[parameters][' + fieldKey + ']" class="small-text" value="' + value + '"';
                    if (field.min) html += ' min="' + field.min + '"';
                    if (field.max) html += ' max="' + field.max + '"';
                    html += '>';
                } else {
                    html += '<input type="' + field.type + '" id="param-' + fieldKey + '" name="feed[parameters][' + fieldKey + ']" class="regular-text" value="' + value + '">';
                }
                
                if (field.description) {
                    html += '<p class="description">' + field.description + '</p>';
                }
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            html += '</div>';
        }
        
        $('#provider-fields').html(html);
    }
    
    // Test provider connection
    $('.test-provider-btn').on('click', function() {
        var formData = new FormData($('#auto-feed-form')[0]);
        var config = {};
        
        // Build config object
        for (var pair of formData.entries()) {
            var keys = pair[0].match(/feed\[([^\]]+)\](?:\[([^\]]+)\])?/);
            if (keys) {
                if (keys[2]) {
                    // Nested field (auth or parameters)
                    if (!config[keys[1]]) config[keys[1]] = {};
                    config[keys[1]][keys[2]] = pair[1];
                } else {
                    // Top level field
                    config[keys[1]] = pair[1];
                }
            }
        }
        
        if (!config.provider_id) {
            alert('<?php _e('Selecione um provedor primeiro', 'job-killer'); ?>');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('Testando...', 'job-killer'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'job_killer_test_provider',
                nonce: '<?php echo wp_create_nonce('job_killer_admin_nonce'); ?>',
                provider_id: config.provider_id,
                config: config
            },
            success: function(response) {
                if (response.success) {
                    $('#test-results').removeClass('notice-error').addClass('notice-success');
                    $('#test-results-content').html(
                        '<p><strong><?php _e('Teste bem-sucedido!', 'job-killer'); ?></strong></p>' +
                        '<p>' + response.data.message + '</p>'
                    );
                    
                    if (response.data.sample_jobs && response.data.sample_jobs.length > 0) {
                        var samplesHtml = '<h4><?php _e('Vagas de exemplo encontradas:', 'job-killer'); ?></h4><ul>';
                        response.data.sample_jobs.forEach(function(job) {
                            samplesHtml += '<li><strong>' + job.title + '</strong>';
                            if (job.company) samplesHtml += ' - ' + job.company;
                            if (job.location) samplesHtml += ' (' + job.location + ')';
                            samplesHtml += '</li>';
                        });
                        samplesHtml += '</ul>';
                        
                        if (response.data.api_url) {
                            samplesHtml += '<p><strong>URL da API:</strong> <code style="word-break: break-all; font-size: 11px;">' + response.data.api_url + '</code></p>';
                        }
                        
                        $('#test-results-content').append(samplesHtml);
                    }
                } else {
                    $('#test-results').removeClass('notice-success').addClass('notice-error');
                    $('#test-results-content').html(
                        '<p><strong><?php _e('Teste falhou!', 'job-killer'); ?></strong></p>' +
                        '<p>' + response.data + '</p>'
                    );
                }
                $('#test-results').show();
            },
            error: function() {
                $('#test-results').removeClass('notice-success').addClass('notice-error');
                $('#test-results-content').html('<p><?php _e('Erro ao testar conexão', 'job-killer'); ?></p>');
                $('#test-results').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php _e('Testar Conexão', 'job-killer'); ?>');
            }
        });
    });
    
    // Save auto feed
    $('.save-auto-feed-btn').on('click', function() {
        var $btn = $(this);
        var $form = $('#auto-feed-form');
        
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Salvando...', 'job-killer'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=job_killer_save_auto_feed&nonce=<?php echo wp_create_nonce('job_killer_admin_nonce'); ?>',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e('Erro ao salvar:', 'job-killer'); ?> ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('Erro ao salvar feed', 'job-killer'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).text(currentFeedId ? '<?php _e('Atualizar Feed', 'job-killer'); ?>' : '<?php _e('Salvar Feed', 'job-killer'); ?>');
            }
        });
    });
    
    // Close modal
    $('.job-killer-modal-close').on('click', function() {
        $('#auto-feed-modal').hide();
        $('#test-results').hide();
    });
    
    // Close modal on background click
    $('#auto-feed-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
            $('#test-results').hide();
        }
    });
    
    // Delete auto feed
    $('.delete-auto-feed').on('click', function() {
        if (!confirm('<?php _e('Tem certeza que deseja excluir este feed?', 'job-killer'); ?>')) {
            return;
        }
        
        var feedId = $(this).data('feed-id');
        var $row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'job_killer_delete_auto_feed',
                nonce: '<?php echo wp_create_nonce('job_killer_admin_nonce'); ?>',
                feed_id: feedId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut();
                } else {
                    alert('<?php _e('Erro ao excluir:', 'job-killer'); ?> ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('Erro ao excluir feed', 'job-killer'); ?>');
            }
        });
    });
    
    // Toggle auto feed
    $('.toggle-auto-feed').on('click', function() {
        var feedId = $(this).data('feed-id');
        var $btn = $(this);
        var $status = $btn.closest('tr').find('.feed-status');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'job_killer_toggle_auto_feed',
                nonce: '<?php echo wp_create_nonce('job_killer_admin_nonce'); ?>',
                feed_id: feedId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.active) {
                        $status.removeClass('inactive').addClass('active').text('<?php _e('Ativo', 'job-killer'); ?>');
                        $btn.text('<?php _e('Desativar', 'job-killer'); ?>');
                    } else {
                        $status.removeClass('active').addClass('inactive').text('<?php _e('Inativo', 'job-killer'); ?>');
                        $btn.text('<?php _e('Ativar', 'job-killer'); ?>');
                    }
                } else {
                    alert('<?php _e('Erro:', 'job-killer'); ?> ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('Erro ao alterar status', 'job-killer'); ?>');
            }
        });
    });
    
    // Import from auto feed
    $('.import-auto-feed').on('click', function() {
        var feedId = $(this).data('feed-id');
        var $btn = $(this);
        
        $btn.prop('disabled', true).text('<?php _e('Importando...', 'job-killer'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'job_killer_import_auto_feed',
                nonce: '<?php echo wp_create_nonce('job_killer_admin_nonce'); ?>',
                feed_id: feedId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('<?php _e('Erro ao importar:', 'job-killer'); ?> ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('Erro ao importar feed', 'job-killer'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php _e('Importar', 'job-killer'); ?>');
            }
        });
    });
});
</script>