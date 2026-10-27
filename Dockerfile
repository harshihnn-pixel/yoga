# For hosting a TEST copy on Render (Web Service -> Docker).
# Official PHP image already includes curl, openssl, mbstring and SQLite.
FROM php:8.3-cli
WORKDIR /app
COPY . /app
RUN mkdir -p donations/api/data && chmod -R 777 donations/api/data
ENV PORT=10000
EXPOSE 10000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} router.php"]
