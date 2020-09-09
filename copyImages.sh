rm -Rf $(pwd)/view/base/web/images

mkdir $(pwd)/view/base/web/images
mkdir $(pwd)/view/base/web/images/methods

cp -Rf $(pwd)/vendor/ccvonlinepayments/images/*.png $(pwd)/view/base/web/images
cp -Rf $(pwd)/vendor/ccvonlinepayments/images/methods/*.png $(pwd)/view/base/web/images/methods
