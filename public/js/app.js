const TodoApp = {
    /**
     * Отправка AJAX запросов
     * @param {string} url - URL запроса
     * @param {string} method - метод запроса
     * @param {Object|null} data - данные для отправки
     * @returns {Promise} - промис с результатом запроса
     */
    sendRequest: function(url, method, data = null) {
        const options = {
            method: method,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        };
        
        if (data) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
        
        return fetch(url, options).then(response => response.json());
    },

    /**
     * Показать уведомление
     * @param {string} type - тип уведомления (success, danger, warning, info)
     * @param {string} message - текст уведомления
     * @param {HTMLElement} container - контейнер для отображения (если не указан, используется body)
     */
    showAlert: function(type, message, container = null) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Добавляем уведомление
        if (container) {
            // Ищем основной контейнер карточек или родительский элемент
            const mainContainer = document.querySelector('#tasks-container, #tags-container');
            if (mainContainer) {
                mainContainer.parentNode.insertBefore(alertDiv, mainContainer);
            } else {
                container.parentNode.insertBefore(alertDiv, container);
            }
        } else {
            // Если контейнер не указан, вставляем в начало body
            document.body.insertBefore(alertDiv, document.body.firstChild);
        }
        
        // Автоматически удаляем через 5 секунд
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    },

    /**
     * Обработка ошибок формы
     * @param {Object} errors - объект с ошибками
     * @param {string} prefix - префикс для ID полей формы
     */
    handleFormErrors: function(errors, prefix = '') {
        // Сначала сбрасываем все ошибки
        document.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
        });
        
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        
        // Затем устанавливаем новые ошибки
        if (errors) {
            Object.keys(errors).forEach(field => {
                const inputField = document.getElementById(`${prefix}${field.charAt(0).toUpperCase() + field.slice(1)}`);
                const errorDisplay = document.getElementById(`${field}Error`);
                
                if (inputField && errorDisplay) {
                    inputField.classList.add('is-invalid');
                    errorDisplay.textContent = errors[field][0];
                }
            });
        }
    }
};

// Функционал для задач
const TaskManager = {
    /**
     * Инициализация менеджера задач
     */
    init: function() {
        this.setupEventListeners();
    },

    /**
     * Настройка обработчиков событий
     */
    setupEventListeners: function() {
        // Здесь можно добавить общие обработчики событий,
        // которые не зависят от конкретного представления
    },

    /**
     * Генерация HTML-кода задачи
     * @param {Object} task - объект задачи
     * @returns {string} - HTML-код для отображения задачи
     */
    generateTaskHtml: function(task) {
        const statusClass = task.status ? 'completed' : '';
        const statusText = task.status ? 'Выполнена' : 'Не выполнена';
        const statusBadgeClass = task.status ? 'bg-success' : 'bg-warning';
        
        // Формирование тегов
        let tagsHtml = '';
        if (task.tags && task.tags.length > 0) {
            task.tags.forEach(function(tag) {
                tagsHtml += `<span class="task-tag">${tag.name}</span>`;
            });
        }
        
        return `
            <div class="card task-card ${statusClass}">
                <div class="card-body">
                    <h5 class="card-title">${task.name}</h5>
                    <p class="card-text">${task.description || 'Без описания'}</p>
                    <div class="task-tags">${tagsHtml || '<em>Нет тегов</em>'}</div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="badge ${statusBadgeClass}">${statusText}</span>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-task-btn" data-task-id="${task.id}">
                                Редактировать
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-task-btn" data-task-id="${task.id}">
                                Удалить
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary mt-2 toggle-status-btn" data-task-id="${task.id}">
                        ${task.status ? 'Отметить как невыполненную' : 'Отметить как выполненную'}
                    </button>
                </div>
            </div>
        `;
    },

    /**
     * Изменение статуса задачи
     * @param {number} taskId - ID задачи
     * @returns {Promise} - промис с результатом запроса
     */
    toggleTaskStatus: function(taskId) {
        return TodoApp.sendRequest(`/tasks/${taskId}/status`, 'PATCH');
    },

    /**
     * Удаление задачи
     * @param {number} taskId - ID задачи
     * @returns {Promise} - промис с результатом запроса
     */
    deleteTask: function(taskId) {
        return TodoApp.sendRequest(`/tasks/${taskId}`, 'DELETE');
    },

    /**
     * Получение задачи по ID
     * @param {number} taskId - ID задачи
     * @returns {Promise} - промис с данными задачи
     */
    getTask: function(taskId) {
        return TodoApp.sendRequest(`/tasks/${taskId}`, 'GET');
    },

    /**
     * Сохранение задачи (создание или обновление)
     * @param {number|null} taskId - ID задачи (null для создания новой)
     * @param {Object} formData - данные формы
     * @returns {Promise} - промис с результатом запроса
     */
    saveTask: function(taskId, formData) {
        const url = taskId ? `/tasks/${taskId}` : '/tasks';
        const method = taskId ? 'PUT' : 'POST';
        
        return TodoApp.sendRequest(url, method, formData);
    }
};

// Функционал для тегов
const TagManager = {
    /**
     * Инициализация менеджера тегов
     */
    init: function() {
        this.setupEventListeners();
    },

    /**
     * Настройка обработчиков событий
     */
    setupEventListeners: function() {
        // Здесь можно добавить общие обработчики событий,
        // которые не зависят от конкретного представления
    },

    /**
     * Удаление тега
     * @param {number} tagId - ID тега
     * @returns {Promise} - промис с результатом запроса
     */
    deleteTag: function(tagId) {
        return TodoApp.sendRequest(`/tags/${tagId}`, 'DELETE');
    },

    /**
     * Сохранение тега (создание или обновление)
     * @param {number|null} tagId - ID тега (null для создания нового)
     * @param {Object} formData - данные формы
     * @returns {Promise} - промис с результатом запроса
     */
    saveTag: function(tagId, formData) {
        const url = tagId ? `/tags/${tagId}` : '/tags';
        const method = tagId ? 'PUT' : 'POST';
        
        return TodoApp.sendRequest(url, method, formData);
    },

    /**
     * Получение всех тегов
     * @returns {Promise} - промис со списком тегов
     */
    getAllTags: function() {
        return TodoApp.sendRequest('/tags/list', 'GET');
    }
};