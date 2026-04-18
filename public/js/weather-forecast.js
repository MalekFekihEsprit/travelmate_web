/**
 * Service de prévisions météo pour TravelMate
 * Utilise l'API OpenWeatherMap (gratuite)
 */
class TravelMateWeather {
    constructor() {
        this.apiKey = null;
        this.cache = new Map();
        this.cacheTimeout = 30 * 60 * 1000; // 30 minutes
        this.isLoading = false;
        this.currentRequest = null;
    }
    
    /**
     * Récupérer les prévisions météo pour une date et localisation
     */
    async getForecast(date, location = 'Tunis') {
        const cacheKey = `${date}-${location}`;
        const cached = this.cache.get(cacheKey);
        
        // Vérifier le cache
        if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
            return cached.data;
        }
        
        // Annuler la requête précédente
        if (this.currentRequest) {
            this.currentRequest.abort();
        }
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const url = `/weather/forecast?date=${encodeURIComponent(date)}&location=${encodeURIComponent(location)}`;
            
            this.currentRequest = new AbortController();
            const response = await fetch(url, {
                signal: this.currentRequest.signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Mettre en cache
            this.cache.set(cacheKey, {
                data: data,
                timestamp: Date.now()
            });
            
            this.isLoading = false;
            this.hideLoading();
            
            return data;
            
        } catch (error) {
            this.isLoading = false;
            this.hideLoading();
            this.showError(error.message);
            throw error;
        }
    }
    
    /**
     * Afficher les prévisions météo dans l'interface
     */
    displayForecast(weatherData, containerId = 'weather-forecast') {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const forecasts = weatherData.forecasts || [];
        
        if (forecasts.length === 0) {
            container.innerHTML = `
                <div class="weather-error">
                    <span class="weather-icon">⚠️</span>
                    <p>Aucune prévision météo disponible pour cette date</p>
                </div>
            `;
            return;
        }
        
        // Calculer les statistiques de la journée
        const temps = forecasts.map(f => f.temperature);
        const minTemp = Math.min(...temps);
        const maxTemp = Math.max(...temps);
        const avgTemp = Math.round(temps.reduce((a, b) => a + b, 0) / temps.length);
        
        // Probabilité maximale de pluie
        const maxRainProbability = Math.max(...forecasts.map(f => f.rain_probability || 0));
        
        // Condition principale de la journée
        const mainCondition = this.getMainCondition(forecasts);
        
        container.innerHTML = `
            <div class="weather-forecast ${weatherData.mock ? 'weather-mock' : ''}">
                <div class="weather-header">
                    <div class="weather-location">
                        <span class="weather-icon">📍</span>
                        <span class="weather-location-name">${weatherData.location}</span>
                        <span class="weather-date">${this.formatDate(weatherData.selected_date)}</span>
                    </div>
                    ${weatherData.mock ? '<span class="weather-badge">Mode démonstration</span>' : ''}
                </div>
                
                <div class="weather-main">
                    <div class="weather-condition">
                        <img src="https://openweathermap.org/img/wn/${mainCondition.icon}@2x.png" 
                             alt="${mainCondition.description}" 
                             class="weather-icon-img">
                        <div class="weather-condition-info">
                            <span class="weather-description">${mainCondition.description}</span>
                            <span class="weather-temperature">${avgTemp}°C</span>
                        </div>
                    </div>
                    
                    <div class="weather-details">
                        <div class="weather-detail-item">
                            <span class="weather-detail-label">Min/Max</span>
                            <span class="weather-detail-value">${minTemp}° / ${maxTemp}°</span>
                        </div>
                        <div class="weather-detail-item">
                            <span class="weather-detail-label">Pluie</span>
                            <span class="weather-detail-value">${maxRainProbability}%</span>
                        </div>
                        <div class="weather-detail-item">
                            <span class="weather-detail-label">Vent</span>
                            <span class="weather-detail-value">${forecasts[0]?.wind_speed || 0} km/h</span>
                        </div>
                    </div>
                </div>
                
                <div class="weather-recommendation">
                    ${this.getRecommendation(mainCondition, maxRainProbability, minTemp, maxTemp)}
                </div>
                
                <div class="weather-hourly">
                    <h4 class="weather-hourly-title">Prévisions par heure</h4>
                    <div class="weather-hourly-grid">
                        ${forecasts.map(forecast => this.createHourlyForecast(forecast)).join('')}
                    </div>
                </div>
            </div>
        `;
        
        // Ajouter les styles si nécessaire
        this.addStyles();
    }
    
    /**
     * Créer une prévision horaire
     */
    createHourlyForecast(forecast) {
        const rainClass = forecast.rain_probability > 50 ? 'weather-rainy' : '';
        const tempClass = forecast.temperature < 10 ? 'weather-cold' : 
                         forecast.temperature > 25 ? 'weather-hot' : '';
        
        return `
            <div class="weather-hourly-item ${rainClass} ${tempClass}">
                <div class="weather-hourly-time">${forecast.time}</div>
                <img src="https://openweathermap.org/img/wn/${forecast.icon}.png" 
                     alt="${forecast.description}" 
                     class="weather-hourly-icon">
                <div class="weather-hourly-temp">${forecast.temperature}°</div>
                ${forecast.rain_probability > 0 ? `
                    <div class="weather-hourly-rain">
                        <span class="weather-rain-icon">💧</span>
                        <span class="weather-rain-percent">${forecast.rain_probability}%</span>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    /**
     * Obtenir la condition météo principale de la journée
     */
    getMainCondition(forecasts) {
        // Compter les occurrences de chaque condition
        const conditions = {};
        forecasts.forEach(f => {
            const desc = f.description.toLowerCase();
            conditions[desc] = (conditions[desc] || 0) + 1;
        });
        
        // Retourner la condition la plus fréquente
        const mainCondition = Object.keys(conditions).reduce((a, b) => 
            conditions[a] > conditions[b] ? a : b
        );
        
        return forecasts.find(f => f.description.toLowerCase() === mainCondition) || forecasts[0];
    }
    
    /**
     * Générer une recommandation selon la météo
     */
    getRecommendation(condition, rainProbability, minTemp, maxTemp) {
        let recommendation = '';
        let icon = '✅';
        
        if (rainProbability > 70) {
            recommendation = 'Fortes pluies prévues. Pensez à reporter votre activité ou à emporter un équipement de pluie.';
            icon = '🌧️';
        } else if (rainProbability > 40) {
            recommendation = 'Risque de pluie. Un parapluie est recommandé.';
            icon = '☔';
        } else if (maxTemp > 35) {
            recommendation = 'Températures très élevées. Privilégiez les activités ombragées et hydratez-vous bien.';
            icon = '🌡️';
        } else if (minTemp < 5) {
            recommendation = 'Températures froides. Habillez-vous chaudement et protégez-vous du vent.';
            icon = '🧥';
        } else if (condition.description.includes('nuage')) {
            recommendation = 'Ciel couvert. Conditions correctes pour la plupart des activités.';
            icon = '☁️';
        } else if (condition.description.includes('soleil')) {
            recommendation = 'Excellent temps ! Conditions idéales pour toutes les activités.';
            icon = '☀️';
        } else {
            recommendation = 'Conditions météo favorables pour votre activité.';
            icon = '🌤️';
        }
        
        return `
            <div class="weather-recommendation-content">
                <span class="weather-recommendation-icon">${icon}</span>
                <span class="weather-recommendation-text">${recommendation}</span>
            </div>
        `;
    }
    
    /**
     * Formater une date pour l'affichage
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('fr-FR', options);
    }
    
    /**
     * Afficher le chargement
     */
    showLoading() {
        const container = document.getElementById('weather-forecast');
        if (container) {
            container.innerHTML = `
                <div class="weather-loading">
                    <div class="weather-spinner"></div>
                    <p>Chargement des prévisions météo...</p>
                </div>
            `;
        }
    }
    
    /**
     * Cacher le chargement
     */
    hideLoading() {
        // Le chargement sera remplacé par les vraies données
    }
    
    /**
     * Afficher une erreur
     */
    showError(message) {
        const container = document.getElementById('weather-forecast');
        if (container) {
            container.innerHTML = `
                <div class="weather-error">
                    <span class="weather-icon">❌</span>
                    <p>Erreur lors du chargement des prévisions météo: ${message}</p>
                    <button onclick="this.parentElement.parentElement.innerHTML = ''" class="weather-close-btn">✕</button>
                </div>
            `;
        }
    }
    
    /**
     * Ajouter les styles CSS
     */
    addStyles() {
        if (document.getElementById('weather-forecast-styles')) return;
        
        const styles = `
            <style id="weather-forecast-styles">
                .weather-forecast {
                    background: var(--color-surface);
                    border: 1px solid var(--color-border);
                    border-radius: var(--radius-lg);
                    padding: 1.5rem;
                    margin: 1rem 0;
                    box-shadow: 0 4px 16px rgba(71,48,35,0.08);
                }
                
                .weather-mock {
                    border-left: 4px solid var(--color-accent);
                }
                
                .weather-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.5rem;
                    flex-wrap: wrap;
                    gap: 1rem;
                }
                
                .weather-location {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .weather-location-name {
                    font-weight: 600;
                    color: var(--color-text);
                }
                
                .weather-date {
                    color: var(--color-text-muted);
                    font-size: 0.9rem;
                }
                
                .weather-badge {
                    background: var(--color-accent);
                    color: var(--color-text);
                    padding: 0.25rem 0.75rem;
                    border-radius: 999px;
                    font-size: 0.75rem;
                    font-weight: 600;
                }
                
                .weather-main {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 2rem;
                    margin-bottom: 1.5rem;
                }
                
                .weather-condition {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                }
                
                .weather-icon-img {
                    width: 64px;
                    height: 64px;
                }
                
                .weather-condition-info {
                    display: flex;
                    flex-direction: column;
                }
                
                .weather-description {
                    font-weight: 500;
                    color: var(--color-text);
                    margin-bottom: 0.5rem;
                }
                
                .weather-temperature {
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--color-primary);
                }
                
                .weather-details {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                }
                
                .weather-detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.5rem 0;
                    border-bottom: 1px solid var(--color-border);
                }
                
                .weather-detail-item:last-child {
                    border-bottom: none;
                }
                
                .weather-detail-label {
                    font-size: 0.9rem;
                    color: var(--color-text-muted);
                }
                
                .weather-detail-value {
                    font-weight: 600;
                    color: var(--color-text);
                }
                
                .weather-recommendation {
                    background: linear-gradient(135deg, rgba(47,127,121,0.1), rgba(196,111,75,0.1));
                    border-radius: var(--radius-md);
                    padding: 1rem;
                    margin-bottom: 1.5rem;
                    border: 1px solid rgba(47,127,121,0.2);
                }
                
                .weather-recommendation-content {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.75rem;
                }
                
                .weather-recommendation-icon {
                    font-size: 1.2rem;
                    flex-shrink: 0;
                }
                
                .weather-recommendation-text {
                    color: var(--color-text);
                    line-height: 1.5;
                }
                
                .weather-hourly-title {
                    font-family: 'Fraunces', serif;
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: var(--color-text);
                    margin-bottom: 1rem;
                }
                
                .weather-hourly-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
                    gap: 0.75rem;
                }
                
                .weather-hourly-item {
                    text-align: center;
                    padding: 0.75rem 0.5rem;
                    border-radius: var(--radius-md);
                    background: var(--color-surface-soft);
                    border: 1px solid var(--color-border);
                    transition: all var(--transition-fast);
                }
                
                .weather-hourly-item:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(71,48,35,0.1);
                }
                
                .weather-rainy {
                    background: rgba(59, 130, 246, 0.1);
                    border-color: rgba(59, 130, 246, 0.3);
                }
                
                .weather-cold {
                    background: rgba(59, 130, 246, 0.1);
                    border-color: rgba(59, 130, 246, 0.3);
                }
                
                .weather-hot {
                    background: rgba(239, 68, 68, 0.1);
                    border-color: rgba(239, 68, 68, 0.3);
                }
                
                .weather-hourly-time {
                    font-size: 0.8rem;
                    color: var(--color-text-muted);
                    margin-bottom: 0.5rem;
                }
                
                .weather-hourly-icon {
                    width: 32px;
                    height: 32px;
                    margin-bottom: 0.5rem;
                }
                
                .weather-hourly-temp {
                    font-weight: 600;
                    color: var(--color-text);
                    margin-bottom: 0.5rem;
                }
                
                .weather-hourly-rain {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.25rem;
                    font-size: 0.75rem;
                }
                
                .weather-rain-icon {
                    color: #3b82f6;
                }
                
                .weather-rain-percent {
                    font-weight: 600;
                    color: #3b82f6;
                }
                
                .weather-loading {
                    text-align: center;
                    padding: 2rem;
                }
                
                .weather-spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid var(--color-border);
                    border-top: 3px solid var(--color-primary);
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 1rem;
                }
                
                .weather-error {
                    text-align: center;
                    padding: 2rem;
                    background: rgba(239, 68, 68, 0.1);
                    border: 1px solid rgba(239, 68, 68, 0.3);
                    border-radius: var(--radius-md);
                    position: relative;
                }
                
                .weather-icon {
                    font-size: 2rem;
                    margin-bottom: 1rem;
                    display: block;
                }
                
                .weather-close-btn {
                    position: absolute;
                    top: 1rem;
                    right: 1rem;
                    background: none;
                    border: none;
                    font-size: 1.2rem;
                    cursor: pointer;
                    color: var(--color-text-muted);
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                @media (max-width: 768px) {
                    .weather-main {
                        grid-template-columns: 1fr;
                        gap: 1rem;
                    }
                    
                    .weather-condition {
                        flex-direction: column;
                        text-align: center;
                    }
                    
                    .weather-hourly-grid {
                        grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
                        gap: 0.5rem;
                    }
                    
                    .weather-hourly-item {
                        padding: 0.5rem 0.25rem;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', styles);
    }
}

// Initialiser le service météo
window.TravelMateWeather = new TravelMateWeather();
