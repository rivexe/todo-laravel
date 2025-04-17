@extends('layouts.app')

@section('content')
    <h1>Управление тегами</h1>
    
    <!-- Список тего -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div id="tags-container" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Дата создания</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="tags-list">
                            </tbody>
                        </table>
                    </div>
                    <div id="no-tags-message" class="alert alert-info" style="display: none;">
                        Теги не найдены. Создайте новый тег!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="button" class="btn btn-primary btn-floating" id="addTagBtn">
        <span class="d-flex align-items-center justify-content-center">+</span>
    </button>
    
    <div class="modal fade" id="tagModal" tabindex="-1" aria-labelledby="tagModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tagModalLabel">Добавить тег</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="tagForm">
                        <input type="hidden" id="tagId" name="tagId" value="">
                        
                        <div class="mb-3">
                            <label for="tagName" class="form-label">Название тега</label>
                            <input type="text" class="form-control" id="tagName" name="name" required>
                            <div class="invalid-feedback" id="nameError"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="saveTagBtn">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для подтверждения удаления -->
    <div class="modal fade" id="deleteTagModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Подтверждение удаления</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить этот тег?</p>
                    <p class="text-warning"><strong>Внимание:</strong> Если тег используется в задачах, его нельзя будет удалить.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Удалить</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tagModal = new bootstrap.Modal(document.getElementById('tagModal'));
        const deleteTagModal = new bootstrap.Modal(document.getElementById('deleteTagModal'));
        let tagIdToDelete = null;
        
        loadTags();
        
        // Обработчикт кнопок
        document.getElementById('addTagBtn').addEventListener('click', function() {
            resetTagForm();
            document.getElementById('tagModalLabel').textContent = 'Добавить тег';
            tagModal.show();
        });
        document.getElementById('saveTagBtn').addEventListener('click', saveTag);
        document.getElementById('confirmDeleteBtn').addEventListener('click', deleteTag);
        document.getElementById('tags-list').addEventListener('click', function(e) {
            // редактирование
            if (e.target.classList.contains('edit-tag-btn') || e.target.closest('.edit-tag-btn')) {
                const btn = e.target.classList.contains('edit-tag-btn') ? e.target : e.target.closest('.edit-tag-btn');
                const tagId = btn.dataset.tagId;
                const tagName = btn.dataset.tagName;
                openEditTagModal(tagId, tagName);
            }
            // удаление
            if (e.target.classList.contains('delete-tag-btn') || e.target.closest('.delete-tag-btn')) {
                const btn = e.target.classList.contains('delete-tag-btn') ? e.target : e.target.closest('.delete-tag-btn');
                tagIdToDelete = btn.dataset.tagId;
                deleteTagModal.show();
            }
        });
        
        // загрузки тегов
        function loadTags() {
            TagManager.getAllTags()
                .then(response => {
                    if (response.tags && response.tags.length > 0) {
                        renderTags(response.tags);
                        document.getElementById('no-tags-message').style.display = 'none';
                        document.getElementById('tags-container').style.display = 'block';
                    } else {
                        document.getElementById('no-tags-message').style.display = 'block';
                        document.getElementById('tags-container').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Ошибка загрузки тегов:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при загрузке тегов');
                });
        }
        
        // рендеринг тегов
        function renderTags(tags) {
            const tagsList = document.getElementById('tags-list');
            tagsList.innerHTML = '';
            
            tags.forEach(tag => {
                const row = document.createElement('tr');
                row.dataset.tagId = tag.id;
                
                const createdAt = new Date(tag.created_at);
                const formattedDate = createdAt.toLocaleDateString('ru-RU') + ' ' + 
                                    createdAt.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'});
                
                row.innerHTML = `
                    <td>${tag.id}</td>
                    <td>${tag.name}</td>
                    <td>${formattedDate}</td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-tag-btn" data-tag-id="${tag.id}" data-tag-name="${tag.name}">
                            Редактировать
                        </button>
                        <button class="btn btn-sm btn-danger delete-tag-btn" data-tag-id="${tag.id}">
                            Удалить
                        </button>
                    </td>
                `;
                
                tagsList.appendChild(row);
            });
        }
        
        // открытие модального окна для редактирования тега
        function openEditTagModal(tagId, tagName) {
            resetTagForm();
            document.getElementById('tagModalLabel').textContent = 'Редактировать тег';
            document.getElementById('tagId').value = tagId;
            document.getElementById('tagName').value = tagName;
            tagModal.show();
        }
        
        // сохранение тега
        function saveTag() {
            const tagForm = document.getElementById('tagForm');
            const tagId = document.getElementById('tagId').value;
            const tagName = document.getElementById('tagName').value;
            
            if (!tagName) {
                document.getElementById('tagName').classList.add('is-invalid');
                document.getElementById('nameError').textContent = 'Название тега обязательно';
                return;
            }

            TagManager.saveTag(tagId, { name: tagName })
                .then(response => {
                    if (response.success) {
                        loadTags();
                        tagModal.hide();
                        TodoApp.showAlert('success', tagId ? 'Тег успешно обновлен' : 'Тег успешно создан');
                        resetTagForm();
                    } else {
                        TodoApp.handleFormErrors(response.errors, 'tag');
                    }
                })
                .catch(error => {
                    console.error('Ошибка сохранения тега:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при сохранении тега');
                });
        }
        
        // Функция удаления тега
        function deleteTag() {
            if (!tagIdToDelete) return;
            TagManager.deleteTag(tagIdToDelete)
                .then(response => {
                    if (response.success) {
                        loadTags();
                        deleteTagModal.hide();
                        TodoApp.showAlert('success', 'Тег успешно удален');
                        tagIdToDelete = null;
                    } else {
                        TodoApp.showAlert('danger', response.message || 'Не удалось удалить тег');
                        deleteTagModal.hide();
                    }
                })
                .catch(error => {
                    console.error('Ошибка удаления тега:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при удалении тега');
                    deleteTagModal.hide();
                });
        }
        
        // сброс форм
        function resetTagForm() {
            const form = document.getElementById('tagForm');
            form.reset();
            document.getElementById('tagId').value = '';
            
            // Очистка сообщений об ошибках
            document.getElementById('nameError').textContent = '';
            document.getElementById('tagName').classList.remove('is-invalid');
        }
    });
</script>
@endsection