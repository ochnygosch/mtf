<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Mtf\Config\FileResolver;

use Magento\Mtf\Util\Iterator\File;
use Magento\Mtf\Config\FileResolverInterface;

/**
 * Class Primary
 * Provides the list of MTF global configuration files
 *
 * @internal
 */
class Primary implements FileResolverInterface
{
    /**
     * Retrieve the configuration files with given name that relate to MTF global configuration
     *
     * @param string $filename
     * @param string $scope
     * @return array
     */
    public function get($filename, $scope)
    {
        if (!$filename) {
            return [];
        }

        $scope = str_replace('\\', '/', $scope);

        if (substr($scope, 0, strlen(MTF_BP)) === MTF_BP) {
            $paths[$scope] = $scope . '/' . $filename;
        } else {
            $mtfDefaultPath = dirname(dirname(dirname(dirname(__DIR__))));
            $mtfDefaultPath = str_replace('\\', '/', $mtfDefaultPath);

            $paths[$mtfDefaultPath] = $mtfDefaultPath . '/' . $scope . '/' . $filename;
            $paths[MTF_BP] = MTF_BP . '/' . $scope . '/' . $filename;

            if (!file_exists($paths[MTF_BP])) {
                unset($paths[MTF_BP]);
            }
        }

        return new File($paths);
    }
}