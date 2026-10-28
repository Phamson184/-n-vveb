FROM php:8.2-cli
# Cài đặt các thư viện cần thiết của Linux và Microsoft ODBC Driver 18
RUN apt-get update && apt-get install -y gnupg2 curl apt-transport-https \
    && curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv
# Copy code vào container
WORKDIR /app
COPY . .
# Chạy PHP Server
CMD ["php", "-S", "0.0.0.0:3000", "index.php"]
