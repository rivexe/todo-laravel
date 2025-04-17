@extends('layouts.app')

@section('content')
    <h1>Список задач</h1>
    <div id="tasks-container" class="row mt-4">
    </div>
    <div id="tasks-loader" class="loader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Загрузка...</span>
        </div>
        <p>Загрузка задач...</p>
    </div>
    <button type="button" class="btn btn-primary btn-floating" id="addTaskBtn">
        <span class="d-flex align-items-center justify-content-center">+</span>
    </button>
    <div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskModalLabel">Добавить задачу</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="taskForm">
                        <input type="hidden" id="taskId" name="taskId" value="">
                        
                        <div class="mb-3">
                            <label for="taskName" class="form-label">Название задачи</label>
                            <input type="text" class="form-control" id="taskName" name="name" required>
                            <div class="invalid-feedback" id="nameError"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="taskDescription" class="form-label">Описание задачи</label>
                            <textarea class="form-control" id="taskDescription" name="description" rows="3"></textarea>
                            <div class="invalid-feedback" id="descriptionError"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="taskTags" class="form-label">Теги</label>
                            <select class="form-select" id="taskTags" name="tag_ids[]" multiple>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="tagIdsError"></div>
                        </div>
                        
                        <div class="mb-3" id="statusContainer" style="display: none;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="taskStatus" name="status">
                                <label class="form-check-label" for="taskStatus">
                                    Задача выполнена
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="saveTaskBtn">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Подтверждение удаления</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить эту задачу?</p>
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
        // Инициализация модальных окон
        const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
        const deleteTaskModal = new bootstrap.Modal(document.getElementById('deleteTaskModal'));
        
        // Select2 для тегов
        const tagsSelect = document.getElementById('taskTags');
        if (typeof Select2 !== 'undefined') {
            new Select2(tagsSelect, {
                theme: 'bootstrap-5',
                placeholder: 'Выберите теги',
                allowClear: true
            });
        }
        
        // Глобальные переменные
        let currentPage = 1;
        let hasMoreTasks = true;
        let isLoading = false;
        let taskIdToDelete = null;
        loadTasks();
        document.getElementById('addTaskBtn').addEventListener('click', function() {
            resetTaskForm();
            document.getElementById('taskModalLabel').textContent = 'Добавить задачу';
            document.getElementById('statusContainer').style.display = 'none';
            taskModal.show();
        });
        document.getElementById('saveTaskBtn').addEventListener('click', saveTask);
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!taskIdToDelete) return;
            
            TaskManager.deleteTask(taskIdToDelete)
                .then(response => {
                    if (response.success) {
                        // Удаляем задачу из DOM
                        const taskItem = document.querySelector(`.task-item[data-task-id="${taskIdToDelete}"]`);
                        if (taskItem) {
                            taskItem.remove();
                        }
                        // Если удалена последняя задача, показываем сообщение
                        if (document.querySelectorAll('.task-item').length === 0) {
                            document.getElementById('tasks-container').innerHTML = 
                                '<div class="col-12"><div class="alert alert-info">Нет задач. Создайте новую задачу!</div></div>';
                        }
                        deleteTaskModal.hide();
                        taskIdToDelete = null;
                        TodoApp.showAlert('success', 'Задача успешно удалена');
                    } else {
                        TodoApp.showAlert('danger', response.message || 'Не удалось удалить задачу');
                        deleteTaskModal.hide();
                    }
                })
                .catch(error => {
                    console.error('Ошибка удаления задачи:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при удалении задачи');
                    deleteTaskModal.hide();
                });
        });
        
        // Делегирование событий для кнопок в списке задач
        document.getElementById('tasks-container').addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-task-btn') || e.target.closest('.edit-task-btn')) {
                const btn = e.target.classList.contains('edit-task-btn') ? e.target : e.target.closest('.edit-task-btn');
                const taskId = btn.dataset.taskId;
                openEditTaskModal(taskId);
            }
            if (e.target.classList.contains('delete-task-btn') || e.target.closest('.delete-task-btn')) {
                const btn = e.target.classList.contains('delete-task-btn') ? e.target : e.target.closest('.delete-task-btn');
                taskIdToDelete = btn.dataset.taskId;
                deleteTaskModal.show();
            }
            if (e.target.classList.contains('toggle-status-btn') || e.target.closest('.toggle-status-btn')) {
                const btn = e.target.classList.contains('toggle-status-btn') ? e.target : e.target.closest('.toggle-status-btn');
                const taskId = btn.dataset.taskId;
                toggleTaskStatus(taskId);
            }
        });
        
        // Обработчик прокрутки для lazy load
        window.addEventListener('scroll', function() {
            if (hasMoreTasks && !isLoading && 
                (window.innerHeight + window.scrollY) >= document.body.offsetHeight - 200) {
                loadTasks(currentPage);
            }
        });
        
        // загрузка задач
        function loadTasks(page = 1) {
            if (isLoading) return;
            
            isLoading = true;
            if (page === 1) {
                document.getElementById('tasks-container').innerHTML = '';
            }
            
            document.getElementById('tasks-loader').style.display = 'block';
            
            fetch(`{{ route("tasks.list") }}?page=${page}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(response => {
                if (response.tasks.length > 0) {
                    response.tasks.forEach(function(task) {
                        appendTask(task);
                    });
                    
                    hasMoreTasks = response.has_more;
                    currentPage = response.next_page;
                } else if (page === 1) {
                    document.getElementById('tasks-container').innerHTML = 
                        '<div class="col-12"><div class="alert alert-info">Нет задач. Создайте новую задачу!</div></div>';
                }
                
                isLoading = false;
                document.getElementById('tasks-loader').style.display = 'none';
            })
            .catch(error => {
                console.error('Ошибка загрузки задач:', error);
                document.getElementById('tasks-container').innerHTML += 
                    '<div class="col-12"><div class="alert alert-danger">Произошла ошибка при загрузке задач</div></div>';
                isLoading = false;
                document.getElementById('tasks-loader').style.display = 'none';
            });
        }
        
        // Функция для добавления задачи в DOM
        function appendTask(task) {
            const taskDiv = document.createElement('div');
            taskDiv.className = 'col-md-6 col-lg-4 task-item';
            taskDiv.dataset.taskId = task.id;
            
            taskDiv.innerHTML = TaskManager.generateTaskHtml(task);
            
            document.getElementById('tasks-container').appendChild(taskDiv);
            return taskDiv;
        }
        
        // Функция сохранения задачи
        function saveTask() {
            const taskForm = document.getElementById('taskForm');
            const taskId = document.getElementById('taskId').value;
            const formData = new FormData(taskForm);
            const selectedTags = Array.from(document.getElementById('taskTags').selectedOptions)
                .map(option => option.value);
            
            // Если используем нативный селект вместо Select2
            formData.delete('tag_ids[]');
            selectedTags.forEach(tagId => {
                formData.append('tag_ids[]', tagId);
            });
            
            // Если это редактирование, добавляем статус
            if (taskId) {
                formData.append('status', document.getElementById('taskStatus').checked ? 1 : 0);
            }
            
            const formDataObj = {};
            formData.forEach((value, key) => {
                // Обработка массивов (например, tag_ids[])
                if (key.endsWith('[]')) {
                    const cleanKey = key.substring(0, key.length - 2);
                    if (!formDataObj[cleanKey]) {
                        formDataObj[cleanKey] = [];
                    }
                    formDataObj[cleanKey].push(value);
                } else {
                    formDataObj[key] = value;
                }
            });

            TaskManager.saveTask(taskId, formDataObj)
                .then(response => {
                    if (response.success) {
                        if (taskId) {
                            // Сохраняем текущую позицию задачи в DOM
                            const taskItem = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
                            const taskParent = taskItem ? taskItem.parentNode : null;
                            const nextSibling = taskItem ? taskItem.nextSibling : null;
                            
                            // Удаляем старый элемент
                            if (taskItem) {
                                taskItem.remove();
                            }
                            
                            // Создаем новый элемент
                            const newTaskElem = document.createElement('div');
                            newTaskElem.className = 'col-md-6 col-lg-4 task-item';
                            newTaskElem.dataset.taskId = response.task.id;
                            
                            // Добавляем HTML для новой задачи
                            newTaskElem.innerHTML = TaskManager.generateTaskHtml(response.task);
                            
                            // Вставляем задачу на прежнее место
                            if (taskParent && nextSibling) {
                                taskParent.insertBefore(newTaskElem, nextSibling);
                            } else if (taskParent) {
                                taskParent.appendChild(newTaskElem);
                            } else {
                                // Если не удалось найти родителя, добавляем в начало списка
                                const tasksContainer = document.getElementById('tasks-container');
                                if (tasksContainer.firstChild) {
                                    tasksContainer.insertBefore(newTaskElem, tasksContainer.firstChild);
                                } else {
                                    tasksContainer.appendChild(newTaskElem);
                                }
                            }
                        } else {
                            // Добавляем задачу в начало списка
                            const tasksContainer = document.getElementById('tasks-container');
                            if (tasksContainer.querySelector('.alert')) {
                                tasksContainer.innerHTML = '';
                            }
                            
                            // Создаем элемент и добавляем его в начало
                            const newTaskElem = document.createElement('div');
                            newTaskElem.className = 'col-md-6 col-lg-4 task-item';
                            newTaskElem.dataset.taskId = response.task.id;
                            newTaskElem.innerHTML = TaskManager.generateTaskHtml(response.task);
                            
                            if (tasksContainer.firstChild) {
                                tasksContainer.insertBefore(newTaskElem, tasksContainer.firstChild);
                            } else {
                                tasksContainer.appendChild(newTaskElem);
                            }
                        }
                        taskModal.hide();
                        resetTaskForm();
                        
                        TodoApp.showAlert('success', taskId ? 'Задача успешно обновлена' : 'Задача успешно создана');
                    } else {
                        // Обработка ошибок валидации
                        TodoApp.handleFormErrors(response.errors, 'task');
                    }
                })
                .catch(error => {
                    console.error('Ошибка сохранения задачи:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при сохранении задачи');
                });
        }
        
        // Функция открытия модального окна для редактирования задачи
        function openEditTaskModal(taskId) {
            resetTaskForm();
            document.getElementById('taskModalLabel').textContent = 'Редактировать задачу';
            document.getElementById('statusContainer').style.display = 'block';
            document.getElementById('taskId').value = taskId;
            
            // Загружаем данные задачи
            TaskManager.getTask(taskId)
                .then(response => {
                    if (response.success && response.task) {
                        const task = response.task;
                        
                        // Заполняем форму
                        document.getElementById('taskName').value = task.name;
                        document.getElementById('taskDescription').value = task.description || '';
                        document.getElementById('taskStatus').checked = task.status;
                        
                        // Заполняем теги
                        const tagSelect = document.getElementById('taskTags');
                        if (task.tags && task.tags.length > 0) {
                            Array.from(tagSelect.options).forEach(option => {
                                option.selected = false;
                            });
                            task.tags.forEach(tag => {
                                const option = tagSelect.querySelector(`option[value="${tag.id}"]`);
                                if (option) {
                                    option.selected = true;
                                }
                            });
                            if (typeof Select2 !== 'undefined') {
                                new Select2(tagSelect).trigger('change');
                            }
                        }
                        taskModal.show();
                    } else {
                        TodoApp.showAlert('danger', 'Не удалось загрузить данные задачи');
                    }
                })
                .catch(error => {
                    console.error('Ошибка загрузки задачи:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при загрузке задачи');
                });
        }
        
        // переключение статуса задачи
        function toggleTaskStatus(taskId) {
            TaskManager.toggleTaskStatus(taskId)
                .then(response => {
                    if (response.success) {
                        // Обновляем задачу в DOM
                        const taskItem = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
                        if (taskItem) {
                            const taskCard = taskItem.querySelector('.task-card');
                            const statusBadge = taskItem.querySelector('.badge');
                            const toggleBtn = taskItem.querySelector('.toggle-status-btn');
                            
                            if (response.task.status) {
                                taskCard.classList.add('completed');
                                statusBadge.className = 'badge bg-success';
                                statusBadge.textContent = 'Выполнена';
                                toggleBtn.textContent = 'Отметить как невыполненную';
                            } else {
                                taskCard.classList.remove('completed');
                                statusBadge.className = 'badge bg-warning';
                                statusBadge.textContent = 'Не выполнена';
                                toggleBtn.textContent = 'Отметить как выполненную';
                            }
                        }
                    } else {
                        TodoApp.showAlert('danger', 'Не удалось изменить статус задачи');
                    }
                })
                .catch(error => {
                    console.error('Ошибка изменения статуса задачи:', error);
                    TodoApp.showAlert('danger', 'Произошла ошибка при изменении статуса задачи');
                });
        }
        
        // сброс формы
        function resetTaskForm() {
            const form = document.getElementById('taskForm');
            form.reset();
            document.getElementById('taskId').value = '';
            const tagSelect = document.getElementById('taskTags');
            Array.from(tagSelect.options).forEach(option => {
                option.selected = false;
            });
            if (typeof Select2 !== 'undefined') {
                new Select2(tagSelect).trigger('change');
            }
            document.querySelectorAll('.invalid-feedback').forEach(el => {
                el.textContent = '';
            });
            
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
        }
    });
</script>
@endsection